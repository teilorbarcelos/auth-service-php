<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Infrastructure\Auth\UserSession;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;

class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $feature,
        private string $action
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var array<string, mixed>|null $user */
        $user = $request->getAttribute('user');
        /** @var array<int, array<string, mixed>> $permissions */
        $permissions = is_array($user) ? ($user['permissions'] ?? []) : [];

        $roleId = is_array($user) ? \App\Infrastructure\Auth\UserSession::resolveRoleId($user) : '';

        if (in_array($roleId, ['admin', 'administrator'], true)) {
            return $handler->handle($request);
        }

        $hasPermission = false;
        foreach ($permissions as $permission) {
            if (is_array($permission) && isset($permission['feature']) && $permission['feature'] === $this->feature) {
                $hasPermission = (bool) ($permission[$this->action] ?? false);
                break;
            }
        }

        if (!$hasPermission) {
            throw new HttpForbiddenException($request, "Sem permissão para {$this->action} em {$this->feature}");
        }

        return $handler->handle($request);
    }
}
