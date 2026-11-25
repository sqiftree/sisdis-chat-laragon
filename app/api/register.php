<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

requireMethod('POST');

try {
    $body = readRequestBody();
    $username = trim((string)($body['username'] ?? ''));
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $displayName = trim((string)($body['display_name'] ?? ''));
    $password = (string)($body['password'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        jsonError('Username harus 3-50 karakter (huruf, angka, atau underscore).', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        jsonError('Format email tidak valid.', 422);
    }
    if ($displayName === '' || mb_strlen($displayName) > 80) {
        jsonError('Nama tampilan wajib diisi (maksimal 80 karakter).', 422);
    }
    if (strlen($password) < 8) {
        jsonError('Password minimal 8 karakter.', 422);
    }

    $pdo = getPdo();

    $check = $pdo->prepare('SELECT id, username, email FROM users WHERE username = :u OR email = :e LIMIT 1');
    $check->execute([':u' => $username, ':e' => $email]);
    $existing = $check->fetch();
    if ($existing) {
        if (strcasecmp($existing['username'], $username) === 0) {
            jsonError('Username sudah terdaftar.', 409);
        }
        jsonError('Email sudah terdaftar.', 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $pdo->prepare(
        'INSERT INTO users (username, email, display_name, password_hash, role, created_at, last_login_at)
         VALUES (:u, :e, :d, :p, :r, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3))'
    );
    $insert->execute([
        ':u' => $username,
        ':e' => $email,
        ':d' => $displayName,
        ':p' => $hash,
        ':r' => 'user',
    ]);

    $newId = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare(
        'SELECT id, username, email, display_name, password_hash, role, created_at, last_login_at
         FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $newId]);
    $user = $stmt->fetch();

    establishUserSession($user);
    $config = appConfig();

    jsonResponse([
        'user' => privateUserView($user),
        'csrf_token' => csrfToken(),
        'node_name' => $config['node_name'],
        'server_time_utc' => databaseTime($config['db']),
    ], 201);
} catch (Throwable $e) {
    jsonError('Gagal mendaftarkan akun. Periksa koneksi database.', 500);
}
