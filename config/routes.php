<?php

declare(strict_types=1);

use Slim\App;
use App\Middleware\AuthMiddleware;

return function (App $app) {
    $app->get('/', function ($request, $response) {
        $response->getBody()->write(json_encode(['name' => 'Auth Service PHP', 'version' => '1.0.0']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/health/live', [\App\Modules\Health\HealthController::class, 'live']);
    $app->get('/health/ready', [\App\Modules\Health\HealthController::class, 'ready']);
    $app->get('/health', [\App\Modules\Health\HealthController::class, 'ready']);

    // API V1 Group
    $app->group('/v1', function ($group) {
        // Health
        $group->get('/health', [\App\Modules\Health\HealthController::class, 'ready']);
        $group->get('/health/live', [\App\Modules\Health\HealthController::class, 'live']);
        $group->get('/health/ready', [\App\Modules\Health\HealthController::class, 'ready']);

        // Auth Feature
        $group->group('/auth', require __DIR__ . '/../src/Modules/Auth/routes.php')
            ->add(\App\Middleware\JsonErrorMiddleware::class);

        // Protected Routes (auth-protected endpoints)
        $group->group('', function ($protectedGroup) {
            // Currently no protected routes — kept for future use
        })->add(AuthMiddleware::class)
            ->add(\App\Middleware\JsonErrorMiddleware::class);

    });
};
