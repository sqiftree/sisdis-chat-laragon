<?php
return [
    'base_url' => '/sisdis-wa-arch/app/public',
    'node_name' => 'laptop-demo',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'sisdis_dev',
        'user' => 'root',
        'pass' => '',
        'ssl_ca' => null, // Untuk cloud gunakan __DIR__ . '/certs/ca.pem'.
    ],
    'backup_service_url' => '',
    'backup_token' => '',
];
