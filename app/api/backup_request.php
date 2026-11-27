<?php
declare(strict_types=1);

/**
 * POST backup_request.php   {csrf_token}
 *      Tombol "Backup Sekarang". App server:
 *        1) catat permintaan di backup_logs (status pending, waktu dari Cloud DB)
 *        2) teruskan via HTTP POST ke Backup Storage (config: backup_service_url)
 *           dengan header X-Backup-Token (config: backup_token)
 *        3) update log jadi success / failed sesuai jawaban backup service
 *
 * GET  backup_request.php
 *      Riwayat backup untuk tabel di UI. User biasa: hanya permintaannya sendiri.
 *      Admin: semua riwayat.
 *
 * Kontrak dengan backup-service/public/backup.php:
 *   request  : JSON {log_id, requested_by, origin_node}
 *   response : HTTP 2xx + JSON {"ok":true,"data":{"filename":"backup_YYYYMMDD_HHMMSS.sql","size_bytes":1234}}
 *   Backup service cukup UPDATE baris backup_logs dengan id = log_id (atau tidak sama sekali),
 *   jangan INSERT baris baru supaya riwayat tidak dobel.
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';

requireMethod('GET', 'POST');

function backupLogView(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'requested_by' => (int)$row['requested_by'],
        'type' => $row['type'],
        'filename' => $row['filename'],
        'status' => $row['status'],
        'size_bytes' => $row['size_bytes'] === null ? null : (int)$row['size_bytes'],
        'message' => $row['message'],
        'created_at_utc' => formatDbUtc($row['created_at']),
    ];
}

try {
    $pdo = getPdo();
    $me = requireAuth($pdo);
    $meId = (int)$me['id'];
    $config = appConfig();

    if (strtoupper($_SERVER['REQUEST_METHOD']) === 'GET') {
        $columns = 'id, requested_by, type, filename, status, size_bytes, message, created_at';
        if ($me['role'] === 'admin') {
            $stmt = $pdo->prepare("SELECT {$columns} FROM backup_logs ORDER BY id DESC LIMIT 50");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT {$columns} FROM backup_logs WHERE requested_by = :me ORDER BY id DESC LIMIT 50");
            $stmt->execute([':me' => $meId]);
        }
        jsonResponse([
            'logs' => array_map('backupLogView', $stmt->fetchAll()),
            'server_time_utc' => databaseTime($config['db']),
        ]);
    }

    // ---------- POST: minta backup ----------
    requireCsrf(readRequestBody());

    $url = trim((string)($config['backup_service_url'] ?? ''));
    if ($url === '') {
        jsonError('backup_service_url belum diisi di app/config/config.local.php.', 500);
    }

    // Cegah dobel klik: tolak kalau masih ada backup pending dalam 2 menit terakhir
    $busy = $pdo->prepare(
        "SELECT id FROM backup_logs
         WHERE type = 'backup' AND status = 'pending'
           AND created_at > UTC_TIMESTAMP(3) - INTERVAL 2 MINUTE
         LIMIT 1"
    );
    $busy->execute();
    if ($busy->fetch()) {
        jsonError('Masih ada proses backup yang berjalan. Tunggu sebentar.', 409);
    }

    // 1) Catat permintaan (created_at otomatis dari Cloud DB)
    $pdo->prepare("INSERT INTO backup_logs (requested_by, type, status, message)
                   VALUES (:me, 'backup', 'pending', :msg)")
        ->execute([':me' => $meId, ':msg' => 'Diminta dari ' . $config['node_name']]);
    $logId = (int)$pdo->lastInsertId();

    // 2) Teruskan ke Backup Storage lewat HTTP
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'log_id' => $logId,
            'requested_by' => $meId,
            'origin_node' => $config['node_name'],
        ], JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Backup-Token: ' . (string)($config['backup_token'] ?? ''),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 120, // mysqldump bisa agak lama
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 3) Simpan hasil
    $reply = is_string($raw) ? json_decode($raw, true) : null;
    $success = $raw !== false && $httpCode >= 200 && $httpCode < 300
        && (!is_array($reply) || ($reply['ok'] ?? true) !== false);

    $data = is_array($reply) ? ($reply['data'] ?? $reply) : [];
    $filename = isset($data['filename']) ? mb_substr((string)$data['filename'], 0, 255) : null;
    $size = isset($data['size_bytes']) && is_numeric($data['size_bytes']) ? (int)$data['size_bytes'] : null;

    if ($success) {
        $message = 'Backup tersimpan di PC backup';
    } elseif ($raw === false) {
        $message = 'PC backup tidak bisa dihubungi: ' . $curlError;
    } else {
        $message = 'PC backup menolak (HTTP ' . $httpCode . '): ' . (string)($reply['error'] ?? 'tanpa keterangan');
    }

    $pdo->prepare('UPDATE backup_logs
                   SET status = :st, filename = COALESCE(:f, filename),
                       size_bytes = COALESCE(:sz, size_bytes), message = :m
                   WHERE id = :id')
        ->execute([
            ':st' => $success ? 'success' : 'failed',
            ':f' => $filename,
            ':sz' => $size,
            ':m' => mb_substr($message, 0, 255),
            ':id' => $logId,
        ]);

    $log = $pdo->prepare('SELECT id, requested_by, type, filename, status, size_bytes, message, created_at
                          FROM backup_logs WHERE id = :id');
    $log->execute([':id' => $logId]);
    $logRow = backupLogView($log->fetch());

    if (!$success) {
        jsonError($message, 502, ['data' => ['log' => $logRow]]);
    }
    jsonResponse([
        'log' => $logRow,
        'server_time_utc' => databaseTime($config['db']),
    ]);
} catch (Throwable $e) {
    jsonError('Gagal memproses permintaan backup.', 500);
}
