<?php

declare(strict_types=1);

namespace App\Modules\User;

use Slim\Routing\RouteCollectorProxy;
use App\Middleware\PermissionMiddleware;

return function (RouteCollectorProxy $group) {
    $group->get('', UserController::class . ':listItems')->add(new PermissionMiddleware('user', 'view'));
    $group->get('/all', UserController::class . ':listAllItems')->add(new PermissionMiddleware('user', 'view'));
    $group->get('/export/pdf', UserController::class . ':exportPdf')->add(new PermissionMiddleware('user', 'view'));
    $group->get('/{id}', UserController::class . ':getById')->add(new PermissionMiddleware('user', 'view'));
    $group->post('', UserController::class . ':create')->add(new PermissionMiddleware('user', 'create'));
    $group->put('/{id}', UserController::class . ':update')->add(new PermissionMiddleware('user', 'create'));
    $group->delete('/{id}', UserController::class . ':delete')->add(new PermissionMiddleware('user', 'delete'));
    $group->patch('/{id}/status', UserController::class . ':toggleStatus')->add(new PermissionMiddleware('user', 'activate'));
};
