<?php
return [
    'mysqldump_path' => 'C:/laragon/bin/mysql/<versi>/bin/mysqldump.exe',
    'mysql_cli_path' => 'C:/laragon/bin/mysql/<versi>/bin/mysql.exe',
    'backup_dir' => 'C:/laragon/backup-sisdis',
    'shared_secret' => '',
    'db' => [
        'host' => '', 'port' => 3306, 'name' => 'sisdis_dev',
        'user' => '', 'pass' => '', 'ssl_ca' => __DIR__ . '/certs/ca.pem',
    ],
];
