<?php

declare(strict_types=1);

use Slim\App;
use App\Middleware\AuthMiddleware;
use App\Core\SwaggerController;

return function (App $app) {
    $app->get('/', function ($request, $response) {
        $response->getBody()->write(json_encode(['name' => 'Backend PHP Slim', 'version' => '1.0.0']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/health/live', [\App\Modules\Health\HealthController::class, 'live']);
    $app->get('/health/ready', [\App\Modules\Health\HealthController::class, 'ready']);
    $app->get('/health', [\App\Modules\Health\HealthController::class, 'ready']);
    $app->get('/metrics', \App\Modules\Metrics\MetricsController::class);

    // API V1 Group
    $app->group('/v1', function ($group) {
        // Health
        $group->get('/health', [\App\Modules\Health\HealthController::class, 'ready']);
        $group->get('/health/live', [\App\Modules\Health\HealthController::class, 'live']);
        $group->get('/health/ready', [\App\Modules\Health\HealthController::class, 'ready']);

        // Swagger
        $group->get('/swagger.json', SwaggerController::class . ':json');
        $group->get('/docs', SwaggerController::class . ':ui');

        // Auth Feature
        $group->group('/auth', require __DIR__ . '/../src/Modules/Auth/routes.php')
            ->add(\App\Middleware\JsonErrorMiddleware::class);

        // Protected Routes
        $group->group('', function ($protectedGroup) {
            // Feature Groups
            $protectedGroup->group('/user', require __DIR__ . '/../src/Modules/User/routes.php');
            $protectedGroup->group('/product', require __DIR__ . '/../src/Modules/Product/routes.php');
            $protectedGroup->group('/feature', require __DIR__ . '/../src/Modules/Feature/routes.php');
            $protectedGroup->group('/role', require __DIR__ . '/../src/Modules/Role/routes.php');
            $protectedGroup->group('/dashboard', require __DIR__ . '/../src/Modules/Dashboard/routes.php');
            // [GENERATOR_ROUTES]

        })->add(AuthMiddleware::class)
            ->add(\App\Middleware\JsonErrorMiddleware::class);

    });
};
