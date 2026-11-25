<?php
declare(strict_types=1);

function appConfig(): array
{
    static $config = null;
    if ($config === null) {
        $local = __DIR__ . '/../config/config.local.php';
        $config = require is_file($local) ? $local : __DIR__ . '/../config/config.example.php';
    }
    return $config;
}

function getPdo(?array $db = null): PDO
{
    static $pool = [];

    $db ??= appConfig()['db'];
    $key = sprintf(
        '%s:%d:%s:%s:%s',
        $db['host'] ?? '',
        (int)($db['port'] ?? 3306),
        $db['name'] ?? '',
        $db['user'] ?? '',
        (string)($db['ssl_ca'] ?? '')
    );

    if (isset($pool[$key])) {
        return $pool[$key];
    }

    $local = in_array($db['host'], ['localhost', '127.0.0.1', '::1'], true);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ];

    if (!$local || !empty($db['ssl_ca'])) {
        if (empty($db['ssl_ca']) || !is_readable($db['ssl_ca'])) {
            throw new RuntimeException('Sertifikat CA diperlukan untuk koneksi database cloud.');
        }
        $options[PDO::MYSQL_ATTR_SSL_CA] = $db['ssl_ca'];
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $db['host'],
        (int)$db['port'],
        $db['name']
    );

    $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
    $pdo->prepare("SET time_zone = '+00:00'")->execute();

    $pool[$key] = $pdo;
    return $pdo;
}

function databaseTime(?array $db = null): string
{
    $pdo = getPdo($db);
    $query = $pdo->prepare('SELECT UTC_TIMESTAMP(3)');
    $query->execute();
    return str_replace(' ', 'T', (string)$query->fetchColumn()) . 'Z';
}
