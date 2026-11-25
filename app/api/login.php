<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

requireMethod('POST');

try {
    $body = readRequestBody();
    $identifier = trim((string)($body['identifier'] ?? $body['username'] ?? $body['email'] ?? ''));
    $password = (string)($body['password'] ?? '');

    if ($identifier === '' || $password === '') {
        jsonError('Username/email dan password wajib diisi.', 422);
    }

    $pdo = getPdo();
    $stmt = $pdo->prepare(
        'SELECT id, username, email, display_name, password_hash, role, created_at, last_login_at
         FROM users
         WHERE username = :id_u OR email = :id_e
         LIMIT 1'
    );
    $stmt->execute([
        ':id_u' => $identifier,
        ':id_e' => strtolower($identifier),
    ]);
    $user = $stmt->fetch();

    if (!$user || $user['password_hash'] === 'disabled') {
        jsonError('Username/email atau password salah.', 401);
    }

    if (!password_verify($password, $user['password_hash'])) {
        jsonError('Username/email atau password salah.', 401);
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $rehash = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
        $rehash->execute([':h' => $newHash, ':id' => (int)$user['id']]);
    }

    // Catat waktu login memakai waktu SERVER CLOUD (UTC_TIMESTAMP(3))
    $updateLogin = $pdo->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP(3) WHERE id = :id');
    $updateLogin->execute([':id' => (int)$user['id']]);

    $refresh = $pdo->prepare(
        'SELECT id, username, email, display_name, password_hash, role, created_at, last_login_at
         FROM users WHERE id = :id LIMIT 1'
    );
    $refresh->execute([':id' => (int)$user['id']]);
    $user = $refresh->fetch();

    establishUserSession($user);
    $config = appConfig();

    jsonResponse([
        'user' => privateUserView($user),
        'csrf_token' => csrfToken(),
        'node_name' => $config['node_name'],
        'server_time_utc' => databaseTime($config['db']),
    ]);
} catch (Throwable $e) {
    jsonError('Gagal memproses login. Periksa koneksi database.', 503);
}
