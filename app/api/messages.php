<?php
declare(strict_types=1);

/**
 * GET  messages.php?with=<id>&after_id=<id>
 *      Polling percakapan dengan user <with>: pesan yang id-nya > after_id.
 *      Client panggil tiap 2-3 detik, after_id = "last_id" dari response sebelumnya.
 *
 * GET  messages.php?with=<id>&before=<id>
 *      Riwayat lama (scroll ke atas): 50 pesan sebelum id <before>.
 *
 * GET  messages.php?after_id=<id>
 *      Polling kotak masuk: semua pesan baru untuk SAYA dari siapa pun
 *      (buat notifikasi / update daftar kontak).
 *
 * POST messages.php   {receiver_id, body, csrf_token}
 *      Kirim pesan. sender_id diambil dari SESI, bukan dari request.
 *      created_at diisi otomatis oleh Cloud DB (DEFAULT CURRENT_TIMESTAMP(3), UTC),
 *      origin_node = node_name app server yang mengirim (dari config.local.php).
 *
 * Privasi (anti-IDOR): setiap query wajib memuat sender_id = saya ATAU receiver_id = saya.
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';

requireMethod('GET', 'POST');

const MESSAGE_COLUMNS = 'id, sender_id, receiver_id, body, origin_node, created_at, delivered_at, read_at';

function messageView(array $row, int $meId): array
{
    return [
        'id' => (int)$row['id'],
        'sender_id' => (int)$row['sender_id'],
        'receiver_id' => (int)$row['receiver_id'],
        'from_me' => (int)$row['sender_id'] === $meId,
        'body' => $row['body'],
        'origin_node' => $row['origin_node'],
        'created_at_utc' => formatDbUtc($row['created_at']),
        'delivered_at_utc' => formatDbUtc($row['delivered_at']),
        'read_at_utc' => formatDbUtc($row['read_at']),
    ];
}

function readIdParam(string $name): ?int
{
    if (!isset($_GET[$name]) || $_GET[$name] === '') {
        return null;
    }
    if (!ctype_digit((string)$_GET[$name])) {
        jsonError("Parameter {$name} tidak valid.", 422);
    }
    return (int)$_GET[$name];
}

try {
    $pdo = getPdo();
    $me = requireAuth($pdo);
    $meId = (int)$me['id'];
    $config = appConfig();

    if (strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') {
        $body = readRequestBody();
        requireCsrf($body);

        $receiverRaw = (string)($body['receiver_id'] ?? '');
        $text = trim((string)($body['body'] ?? ''));

        if (!ctype_digit($receiverRaw) || (int)$receiverRaw === $meId) {
            jsonError('Penerima tidak valid.', 422);
        }
        if ($text === '' || mb_strlen($text) > 2000) {
            jsonError('Pesan wajib diisi (1-2000 karakter).', 422);
        }

        $check = $pdo->prepare("SELECT id FROM users WHERE id = :id AND password_hash <> 'disabled' LIMIT 1");
        $check->execute([':id' => (int)$receiverRaw]);
        if (!$check->fetch()) {
            jsonError('Penerima tidak ditemukan.', 404);
        }

        // created_at TIDAK diisi dari PHP: biar Cloud DB yang isi (acuan waktu sistem)
        $insert = $pdo->prepare(
            'INSERT INTO messages (sender_id, receiver_id, body, origin_node)
             VALUES (:s, :r, :b, :n)'
        );
        $insert->execute([
            ':s' => $meId,
            ':r' => (int)$receiverRaw,
            ':b' => $text,
            ':n' => mb_substr((string)$config['node_name'], 0, 50),
        ]);

        $saved = $pdo->prepare('SELECT ' . MESSAGE_COLUMNS . ' FROM messages WHERE id = :id AND sender_id = :me');
        $saved->execute([':id' => (int)$pdo->lastInsertId(), ':me' => $meId]);

        jsonResponse([
            'message' => messageView($saved->fetch(), $meId),
            'node_name' => $config['node_name'],
            'server_time_utc' => databaseTime($config['db']),
        ], 201);
    }

    // ---------- GET ----------
    $withId = readIdParam('with');
    $afterId = readIdParam('after_id');
    $beforeId = readIdParam('before');

    if ($withId !== null) {
        // Percakapan 1-on-1 antara SAYA dan $withId
        $where = '((sender_id = :me1 AND receiver_id = :w1) OR (sender_id = :w2 AND receiver_id = :me2))';
        $params = [':me1' => $meId, ':w1' => $withId, ':w2' => $withId, ':me2' => $meId];

        if ($beforeId !== null) {
            $sql = 'SELECT * FROM (SELECT ' . MESSAGE_COLUMNS . " FROM messages WHERE {$where} AND id < :before
                    ORDER BY id DESC LIMIT 50) older ORDER BY id ASC";
            $params[':before'] = $beforeId;
        } elseif ($afterId !== null) {
            $sql = 'SELECT ' . MESSAGE_COLUMNS . " FROM messages WHERE {$where} AND id > :after ORDER BY id ASC LIMIT 200";
            $params[':after'] = $afterId;
        } else {
            // Buka chat pertama kali: 50 pesan terakhir
            $sql = 'SELECT * FROM (SELECT ' . MESSAGE_COLUMNS . " FROM messages WHERE {$where}
                    ORDER BY id DESC LIMIT 50) latest ORDER BY id ASC";
        }
    } else {
        // Polling kotak masuk: pesan baru untuk SAYA dari siapa pun
        $sql = 'SELECT ' . MESSAGE_COLUMNS . ' FROM messages WHERE receiver_id = :me AND id > :after ORDER BY id ASC LIMIT 200';
        $params = [':me' => $meId, ':after' => $afterId ?? 0];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Lapisan pengaman kedua: pastikan tiap baris memang milik percakapan saya
    foreach ($rows as $row) {
        assertMessageAccess($row, $me);
    }

    // Tandai pesan masuk sebagai "terkirim ke perangkat" pakai waktu Cloud DB
    $incoming = array_values(array_map(
        fn($r) => (int)$r['id'],
        array_filter($rows, fn($r) => (int)$r['receiver_id'] === $meId && $r['delivered_at'] === null)
    ));
    if ($incoming) {
        $marks = implode(',', array_fill(0, count($incoming), '?'));
        $pdo->prepare("UPDATE messages SET delivered_at = UTC_TIMESTAMP(3)
                       WHERE receiver_id = ? AND delivered_at IS NULL AND id IN ({$marks})")
            ->execute(array_merge([$meId], $incoming));
    }

    $lastId = $rows ? (int)end($rows)['id'] : ($afterId ?? 0);

    jsonResponse([
        'messages' => array_map(fn($r) => messageView($r, $meId), $rows),
        'last_id' => $lastId,
        'node_name' => $config['node_name'],
        'server_time_utc' => databaseTime($config['db']),
    ]);
} catch (Throwable $e) {
    jsonError('Gagal memproses pesan. Periksa koneksi database.', 500);
}
