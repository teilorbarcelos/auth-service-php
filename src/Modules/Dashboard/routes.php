<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use Slim\Routing\RouteCollectorProxy;
use App\Middleware\PermissionMiddleware;

return function (RouteCollectorProxy $group) {
    $group->get('/stats', DashboardController::class . ':getStats')->add(new PermissionMiddleware('dashboard', 'view'));
};
