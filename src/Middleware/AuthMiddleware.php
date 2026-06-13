<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Infrastructure\Auth\JwtService;
use App\Infrastructure\Auth\UserSession;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpUnauthorizedException;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private JwtService $jwtService,
        private UserSession $userSession
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->userSession->setUser(null);
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new HttpUnauthorizedException($request, 'Authorization token missing');
        }

        $token = substr($authHeader, 7);
        $claims = $this->jwtService->validateToken($token);

        if (!$claims) {
            throw new HttpUnauthorizedException($request, 'Invalid or expired token');
        }

        $userId = $claims['uid'] ?? null;

        if (!is_string($userId) || !$this->jwtService->isTokenValid($userId, $token)) {
            throw new HttpUnauthorizedException($request, 'Session revoked or invalid');
        }

        $this->userSession->setUser($claims);

        $request = $request->withAttribute('userId', $userId)
            ->withAttribute('user', $claims);

        return $handler->handle($request);
    }
}
