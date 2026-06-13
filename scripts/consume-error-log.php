#!/usr/bin/env php
<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    require_once __DIR__ . '/../scripts/load-env.php';
    loadEnvironment(__DIR__ . '/../');
}

$containerBuilder = new ContainerBuilder();
$settings = require __DIR__ . '/../config/container.php';
$containerBuilder->addDefinitions($settings);
$container = $containerBuilder->build();
$container->get('db');

if (($_ENV['MESSAGING_ENABLED'] ?? 'false') !== 'true') {
    echo "[ErrorLogConsumer] MESSAGING_ENABLED is not true. Exiting.\n";
    exit(0);
}

$provider = $container->get(\App\Infrastructure\Messaging\RabbitMQProvider::class);
$provider->connect();

echo "[ErrorLogConsumer] Listening on 'error_log' queue...\n";

$provider->subscribe('error_log', function (array $message): void {
    try {
        \App\Modules\Audit\ErrorLog::create([
            'id_user' => $message['id_user'] ?? null,
            'source' => $message['source'] ?? '',
            'error_message' => $message['error_message'] ?? '',
            'error_data' => $message['error_data'] ?? [],
        ]);
    } catch (\Throwable $e) {
        echo "[ErrorLogConsumer] Error processing error log: {$e->getMessage()}\n";
    }
});
