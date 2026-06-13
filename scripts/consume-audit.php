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
    echo "[AuditConsumer] MESSAGING_ENABLED is not true. Exiting.\n";
    exit(0);
}

$provider = $container->get(\App\Infrastructure\Messaging\RabbitMQProvider::class);
$provider->connect();

echo "[AuditConsumer] Listening on 'audit' queue...\n";

$provider->subscribe('audit', function (array $message): void {
    try {
        \App\Modules\Audit\Audit::create($message);
    } catch (\Throwable $e) {
        echo "[AuditConsumer] Error processing audit: {$e->getMessage()}\n";
    }
});
