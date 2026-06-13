<?php

declare(strict_types=1);

namespace App\Modules\{{MODULE_NAME}};

use Slim\Routing\RouteCollectorProxy;
use App\Modules\{{MODULE_NAME}}\{{MODULE_NAME}}Controller;

return function (RouteCollectorProxy $group) {
    $group->get('', {{MODULE_NAME}}Controller::class . ':listItems');
    $group->get('/all', {{MODULE_NAME}}Controller::class . ':listAllItems');
    $group->get('/{id}', {{MODULE_NAME}}Controller::class . ':getById');
    $group->post('', {{MODULE_NAME}}Controller::class . ':create');
    $group->put('/{id}', {{MODULE_NAME}}Controller::class . ':update');
    $group->delete('/{id}', {{MODULE_NAME}}Controller::class . ':delete');
    $group->patch('/{id}/status', {{MODULE_NAME}}Controller::class . ':toggleStatus');
};
