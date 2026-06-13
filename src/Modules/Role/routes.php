<?php

declare(strict_types=1);

namespace App\Modules\Role;

use Slim\Routing\RouteCollectorProxy;
use App\Middleware\PermissionMiddleware;

return function (RouteCollectorProxy $group) {
    $group->get('/features', RoleController::class . ':listFeatures')->add(new PermissionMiddleware('role', 'view'));
    $group->get('', RoleController::class . ':listItems')->add(new PermissionMiddleware('role', 'view'));
    $group->get('/all', RoleController::class . ':listAllItems')->add(new PermissionMiddleware('role', 'view'));
    $group->get('/{id}', RoleController::class . ':getById')->add(new PermissionMiddleware('role', 'view'));
    $group->post('', RoleController::class . ':create')->add(new PermissionMiddleware('role', 'create'));
    $group->put('/{id}', RoleController::class . ':update')->add(new PermissionMiddleware('role', 'create'));
    $group->delete('/{id}', RoleController::class . ':delete')->add(new PermissionMiddleware('role', 'delete'));
    $group->patch('/{id}/status', RoleController::class . ':toggleStatus')->add(new PermissionMiddleware('role', 'activate'));
};
