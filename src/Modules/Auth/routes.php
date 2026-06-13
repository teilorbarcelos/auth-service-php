<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use Slim\Routing\RouteCollectorProxy;
use App\Modules\Auth\AuthController;
use App\Middleware\AuthMiddleware;

return function (RouteCollectorProxy $group) {
    $group->post('/login', AuthController::class . ':login');
    $group->post('/refresh', AuthController::class . ':refresh');
    $group->get('/me', AuthController::class . ':me')->add(AuthMiddleware::class);

    $group->post('/password/request', AuthController::class . ':requestPasswordReset');
    $group->post('/password/validate', AuthController::class . ':validateResetToken');
    $group->post('/password/change', AuthController::class . ':resetPassword');
};
