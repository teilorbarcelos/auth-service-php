<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;

if (file_exists(__DIR__ . '/../.env')) {
    require_once __DIR__ . '/load-env.php';
    loadEnvironment(__DIR__ . '/../');
}

$containerBuilder = new ContainerBuilder();
$settings = require __DIR__ . '/../config/container.php';
$containerBuilder->addDefinitions($settings);
$container = $containerBuilder->build();

$container->get('db');

\App\Core\DatabaseBootstrap::init();

echo "Application initialized: admin user and roles seeded.\n";
