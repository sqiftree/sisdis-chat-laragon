<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

requireMethod('GET');

try {
    $pdo = getPdo();
    $actor = requireAuth($pdo);

    $rawId = $_GET['user_id'] ?? (string)$actor['id'];
    if (!ctype_digit((string)$rawId)) {
        jsonError('Parameter user_id tidak valid.', 422);
    }

    $targetUserId = (int)$rawId;

    // Pengecekan privasi (Anti-IDOR): tolak 403 jika mencoba mengakses user_id orang lain
    assertSelfAccess($targetUserId, $actor);

    $config = appConfig();
    jsonResponse([
        'user' => privateUserView($actor),
        'node_name' => $config['node_name'],
        'server_time_utc' => databaseTime($config['db']),
    ]);
} catch (Throwable $e) {
    jsonError('Gagal mengambil data pribadi.', 500);
}
