<?php
declare(strict_types=1);

function appConfig(): array
{
    $local = __DIR__ . '/../config/config.local.php';
    return require is_file($local) ? $local : __DIR__ . '/../config/config.example.php';
}

function databaseTime(array $db): string
{
    $local = in_array($db['host'], ['localhost', '127.0.0.1', '::1'], true);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ];
    if (!$local || !empty($db['ssl_ca'])) {
        if (empty($db['ssl_ca']) || !is_readable($db['ssl_ca'])) {
            throw new RuntimeException('Sertifikat CA diperlukan.');
        }
        $options[PDO::MYSQL_ATTR_SSL_CA] = $db['ssl_ca'];
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);
    $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
    $pdo->prepare("SET time_zone = '+00:00'")->execute();
    $query = $pdo->prepare("SELECT UTC_TIMESTAMP(3)");
    $query->execute();
    return str_replace(' ', 'T', $query->fetchColumn()) . 'Z';
}
