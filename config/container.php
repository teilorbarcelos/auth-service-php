<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Container\ContainerInterface;

return [
    'db' => function (ContainerInterface $container) {
        $provider = \App\Infrastructure\Database\DatabaseProvider::getInstance();
        $capsule = $provider->getCapsule();

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

    // Services
    \App\Infrastructure\Auth\JwtService::class => \DI\autowire()->constructorParameter('logger', \DI\get(\Psr\Log\LoggerInterface::class)),
    \App\Modules\Auth\AuthService::class => \DI\autowire(),
    \App\Modules\User\UserRepository::class => \DI\autowire(),
    \App\Modules\Feature\FeatureRepository::class => \DI\autowire(),
    \App\Modules\Role\RoleRepository::class => \DI\autowire(),
    // [GENERATOR_SERVICES]

    // Middlewares
    \App\Middleware\AuthMiddleware::class => \DI\autowire(),
    \App\Middleware\RateLimitMiddleware::class => \DI\autowire(),
    \App\Middleware\LogMiddleware::class => \DI\autowire(),
    \App\Middleware\JsonErrorMiddleware::class => \DI\autowire(),
    \App\Middleware\BodySizeLimitMiddleware::class => \DI\autowire(),
];
