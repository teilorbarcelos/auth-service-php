<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Container\ContainerInterface;

return [
    'db' => function (ContainerInterface $container) {
        $provider = \App\Infrastructure\Database\DatabaseProvider::getInstance();
        $capsule = $provider->getCapsule();

        $provider->listenQueryExecuted(
            function (\Illuminate\Database\Events\QueryExecuted $event) use ($container) {
                try {
                    $metricService = $container->get(\App\Infrastructure\Metrics\MetricService::class);
                    $metricService->incrementCounter('database_queries_total');
                } catch (\Throwable $e) {
                    // Do not fail the request if metrics tracking fails
                }
            }
        );

        return $capsule;
    },

    Capsule::class => \DI\get('db'),

    \App\Infrastructure\Auth\UserSession::class => function () {
        $session = new \App\Infrastructure\Auth\UserSession();
        \App\Infrastructure\Auth\UserSession::setInstance($session);
        return $session;
    },

    \Redis::class => function () {
        $redis = new \Redis();
        $redis->connect(
            getenv('REDIS_HOST') ?: 'redis',
            (int) (getenv('REDIS_PORT') ?: 6379),
            2.5
        );
        return $redis;
    },

    'redis' => \DI\get(\Redis::class),

    \App\Infrastructure\Log\RequestIdProcessor::class => \DI\create(),

    \Psr\Log\LoggerInterface::class => function (ContainerInterface $container) {
        $logger = new \Monolog\Logger('api');
        $handler = new \Monolog\Handler\BufferHandler(
            new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Level::Debug)
                ->setFormatter(new \Monolog\Formatter\JsonFormatter()),
            100,
            \Monolog\Level::Debug,
            true,
            true
        );
        $logger->pushHandler($handler);
        $logger->pushProcessor($container->get(\App\Infrastructure\Log\RequestIdProcessor::class));
        return $logger;
    },

    \App\Infrastructure\Metrics\MetricService::class => function (ContainerInterface $container) {
        return new \App\Infrastructure\Metrics\MetricService([
            'host' => getenv('REDIS_HOST') ?: 'redis',
            'port' => getenv('REDIS_PORT') ?: 6379,
            'persistent_connections' => true,
        ]);
    },

    // Services
    \App\Modules\Audit\AuditObserver::class => \DI\autowire(),
    \App\Infrastructure\Audit\ErrorAuditService::class => \DI\autowire(),
    \App\Infrastructure\Email\EmailProvider::class => \DI\autowire(),
    \App\Infrastructure\Messaging\RabbitMQProvider::class => \DI\autowire(),
    \App\Infrastructure\Storage\StorageProvider::class => \DI\autowire(),
    \App\Infrastructure\Auth\JwtService::class => \DI\autowire()->constructorParameter('logger', \DI\get(\Psr\Log\LoggerInterface::class)),
    \App\Modules\Auth\AuthService::class => \DI\autowire(),
    \App\Modules\User\UserService::class => \DI\autowire(),
    \App\Modules\User\UserRepository::class => \DI\autowire(),
    \App\Modules\Product\ProductService::class => \DI\autowire(),
    \App\Modules\Product\ProductRepository::class => \DI\autowire(),
    \App\Modules\Feature\FeatureService::class => \DI\autowire(),
    \App\Modules\Feature\FeatureRepository::class => \DI\autowire(),
    \App\Modules\Role\RoleService::class => \DI\autowire(),
    \App\Modules\Role\RoleRepository::class => \DI\autowire(),
    \App\Modules\Metrics\MetricsController::class => \DI\autowire(),
    \App\Infrastructure\Pdf\PdfProviderInterface::class => \DI\get(\App\Infrastructure\Pdf\RemotePdfProvider::class),
    \App\Infrastructure\Pdf\RemotePdfProvider::class => \DI\autowire(),
    \App\Modules\Dashboard\DashboardController::class => \DI\autowire(),
    \App\Modules\Dashboard\DashboardService::class => \DI\autowire(),
    // [GENERATOR_SERVICES]

    // Middlewares
    \App\Middleware\AuthMiddleware::class => \DI\autowire(),
    \App\Middleware\RateLimitMiddleware::class => \DI\autowire(),
    \App\Middleware\LogMiddleware::class => \DI\autowire(),
    \App\Middleware\JsonErrorMiddleware::class => \DI\autowire(),
    \App\Middleware\BodySizeLimitMiddleware::class => \DI\autowire(),
];
