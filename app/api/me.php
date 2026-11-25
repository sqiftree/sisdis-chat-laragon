<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

requireMethod('GET', 'POST', 'PATCH');

try {
    $pdo = getPdo();
    $user = requireAuth($pdo);
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $config = appConfig();

    if ($method === 'GET') {
        jsonResponse([
            'user' => privateUserView($user),
            'csrf_token' => csrfToken(),
            'node_name' => $config['node_name'],
            'server_time_utc' => databaseTime($config['db']),
        ]);
    }

    // Update profil pribadi sendiri (display_name dan/atau ganti password)
    $body = readRequestBody();
    $displayName = isset($body['display_name']) ? trim((string)$body['display_name']) : $user['display_name'];
    if ($displayName === '' || mb_strlen($displayName) > 80) {
        jsonError('Nama tampilan wajib diisi (maksimal 80 karakter).', 422);
    }

    $newPassword = (string)($body['new_password'] ?? '');
    if ($newPassword !== '') {
        $currentPassword = (string)($body['current_password'] ?? '');
        if (!password_verify($currentPassword, $user['password_hash'])) {
            jsonError('Password saat ini tidak sesuai.', 403);
        }
        if (strlen($newPassword) < 8) {
            jsonError('Password baru minimal 8 karakter.', 422);
        }
        $upd = $pdo->prepare('UPDATE users SET display_name = :d, password_hash = :p WHERE id = :id');
        $upd->execute([
            ':d' => $displayName,
            ':p' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':id' => (int)$user['id'],
        ]);
    } else {
        $upd = $pdo->prepare('UPDATE users SET display_name = :d WHERE id = :id');
        $upd->execute([
            ':d' => $displayName,
            ':id' => (int)$user['id'],
        ]);
    }

    $stmt = $pdo->prepare(
        'SELECT id, username, email, display_name, password_hash, role, created_at, last_login_at
         FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => (int)$user['id']]);
    $updated = $stmt->fetch();

    jsonResponse([
        'user' => privateUserView($updated),
        'node_name' => $config['node_name'],
        'server_time_utc' => databaseTime($config['db']),
    ]);
} catch (Throwable $e) {
    jsonError('Gagal memuat atau memperbarui profil pribadi.', 500);
}
