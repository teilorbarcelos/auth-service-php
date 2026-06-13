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
    echo "[EmailConsumer] MESSAGING_ENABLED is not true. Exiting.\n";
    exit(0);
}

$provider = $container->get(\App\Infrastructure\Messaging\RabbitMQProvider::class);
$provider->connect();

echo "[EmailConsumer] Listening on 'email' queue...\n";

$provider->subscribe('email', function (array $message): void {
    try {
        $provider = new \App\Infrastructure\Email\EmailProvider();
        $provider->sendEmail($message['to'] ?? '', $message['subject'] ?? '', $message['html'] ?? '');
    } catch (\Throwable $e) {
        echo "[EmailConsumer] Error sending email: {$e->getMessage()}\n";
    }
});
