<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

if (file_exists(__DIR__ . '/../.env')) {
    require_once __DIR__ . '/../scripts/load-env.php';
    loadEnvironment(__DIR__ . '/../');
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

$containerBuilder = new ContainerBuilder();
$settings = require __DIR__ . '/../config/container.php';
$containerBuilder->addDefinitions($settings);

$container = $containerBuilder->build();

AppFactory::setContainer($container);

// Graceful shutdown handler (pcntl disponível no worker mode FrankenPHP)
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function () use (&$running) {
        $running = false;
    });
}

// Build app once (shared across all requests in worker mode)
$app = AppFactory::create();

// Initialize DB connection once per worker (not per request)
try {
    $container->get('db');
    \App\Core\DatabaseBootstrap::init();
} catch (\Exception $e) {
    error_log("Init failed: " . $e->getMessage());
}

// Wire async audit observer (via container, não mais via boot estático em BaseModel)
try {
    $observer = $container->get(\App\Modules\Audit\AuditObserver::class);
    foreach ([\App\Modules\User\User::class, \App\Modules\Product\Product::class, \App\Modules\Role\Role::class, \App\Modules\Feature\Feature::class] as $modelClass) {
        $modelClass::observe($observer);
    }
} catch (\Exception $e) {
    error_log("AuditObserver init failed: " . $e->getMessage());
}

// Register routes
$routes = require __DIR__ . '/../config/routes.php';
$routes($app);

// MIDDLEWARE ORDER (same as index.php)
$app->add(\App\Middleware\BodySizeLimitMiddleware::class);
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(\App\Middleware\TrailingSlashMiddleware::class);
$app->add(\App\Middleware\LogMiddleware::class);
$app->addErrorMiddleware(
    ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    true,
    true
);
$app->add(\App\Middleware\RateLimitMiddleware::class);
$app->add(\App\Middleware\CorsMiddleware::class);

// Worker loop
$running = true;
while ($running) {
    $request = \FrankenPHP\ServerRequestFactory::fromGlobals();
    $app->handle($request);
    \FrankenPHP\Kernel::afterRequest();
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
}
