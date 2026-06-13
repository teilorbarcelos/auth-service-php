<?php

require __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => getenv('DB_DRIVER') ?: 'pgsql',
            'host' => getenv('DB_HOST') ?: 'db',
            'name' => getenv('DB_DATABASE') ?: 'backend_php_slim_db',
            'user' => getenv('DB_USERNAME') ?: 'postgres',
            'pass' => getenv('DB_PASSWORD') ?: 'postgrespw',
            'port' => getenv('DB_PORT') ?: '5432',
            'charset' => getenv('DB_CHARSET') ?: 'utf8',
        ]
    ],
    'version_order' => 'creation'
];
