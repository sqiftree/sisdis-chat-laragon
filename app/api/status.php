<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    $config = appConfig();
    $start = hrtime(true);
    $utc = databaseTime($config['db']);
    echo json_encode(['ok' => true, 'data' => [
        'node_name' => $config['node_name'], 'server_time_utc' => $utc,
        'fetched_in_ms' => round((hrtime(true) - $start) / 1e6),
    ]], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database belum terhubung. Periksa layanan MySQL, ekstensi PDO MySQL, dan konfigurasi koneksi.']);
}
