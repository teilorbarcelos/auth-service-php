<?php

declare(strict_types=1);

namespace App\Modules\Feature;

use Slim\Routing\RouteCollectorProxy;
use App\Middleware\PermissionMiddleware;

return function (RouteCollectorProxy $group) {
    $group->get('', FeatureController::class . ':listItems')->add(new PermissionMiddleware('feature', 'view'));
    $group->get('/all', FeatureController::class . ':listAllItems')->add(new PermissionMiddleware('feature', 'view'));
    $group->get('/{id}', FeatureController::class . ':getById')->add(new PermissionMiddleware('feature', 'view'));
};
