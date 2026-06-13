<?php

declare(strict_types=1);

namespace App\Modules\Product;

use Slim\Routing\RouteCollectorProxy;
use App\Middleware\PermissionMiddleware;

return function (RouteCollectorProxy $group) {
    $group->get('', ProductController::class . ':listItems')->add(new PermissionMiddleware('product', 'view'));
    $group->get('/all', ProductController::class . ':listAllItems')->add(new PermissionMiddleware('product', 'view'));
    $group->get('/{id}', ProductController::class . ':getById')->add(new PermissionMiddleware('product', 'view'));
    $group->post('', ProductController::class . ':create')->add(new PermissionMiddleware('product', 'create'));
    $group->put('/{id}', ProductController::class . ':update')->add(new PermissionMiddleware('product', 'create'));
    $group->delete('/{id}', ProductController::class . ':delete')->add(new PermissionMiddleware('product', 'delete'));
    $group->patch('/{id}/status', ProductController::class . ':toggleStatus')->add(new PermissionMiddleware('product', 'activate'));
};
