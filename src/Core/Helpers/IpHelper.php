<?php

declare(strict_types=1);

namespace App\Core\Helpers;

use Psr\Http\Message\ServerRequestInterface;

class IpHelper
{
    public static function getClientIp(ServerRequestInterface $request): string
    {
        $params = $request->getServerParams();
        return $params['HTTP_X_FORWARDED_FOR'] ?? $params['REMOTE_ADDR'] ?? 'unknown';
    }
}
