<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Redis;
use Slim\Psr7\Response as SlimResponse;

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $limit;
    private int $window;

    public function __construct(
        private Redis $redis,
        private ?\App\Infrastructure\Auth\JwtService $jwtService = null
    ) {
        $this->limit = (int) ($_ENV['RATE_LIMIT_MAX'] ?? 60);
        $this->window = (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60);
    }

    public function process(Request $request, Handler $handler): Response
    {
        $route = $request->getUri()->getPath();
        if (in_array($route, ['/health', '/metrics', '/v1/health'], true) || str_starts_with($route, '/v1/docs') || str_starts_with($route, '/v1/swagger')) {
            return $handler->handle($request);
        }

        /** @var array<string, mixed>|null $user */
        $user = $request->getAttribute('user');
        $roleId = is_array($user) ? \App\Infrastructure\Auth\UserSession::resolveRoleId($user) : '';
        $isAdmin = in_array($roleId, ['admin', 'administrator'], true);

        if ($this->isAdminRequest($request) || $isAdmin) {
            $response = $handler->handle($request);
            return $response
                ->withHeader('X-RateLimit-Limit', (string) $this->limit)
                ->withHeader('X-RateLimit-Remaining', (string) $this->limit)
                ->withHeader('X-RateLimit-Reset', (string) ($this->window));
        }

        $ip = \App\Core\Helpers\IpHelper::getClientIp($request);
        /** @var string|null $userId */
        $userId = $request->getAttribute('userId');
        $key = "rate_limit:{$ip}:{$route}";
        if ($userId !== null) {
            $key = "rate_limit:user:{$userId}:{$route}";
        }

        $current = $this->redis->incr($key);
        if (!is_int($current)) {
            $current = 0;
        }
        if ($current === 1) {
            $this->redis->expire($key, $this->window);
        }

        if ($current > $this->limit) {
            $response = new SlimResponse();
            $json = json_encode([
                'error' => 'Too Many Requests',
                'message' => "Rate limit exceeded. Try again in some seconds.",
                'limit' => $this->limit,
                'window' => $this->window . 's'
            ]);
            $response->getBody()->write($json ?: '{}');

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(429)
                ->withHeader('X-RateLimit-Limit', (string) $this->limit)
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        $remaining = max(0, $this->limit - $current);
        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) ($this->window));
    }

    private function isAdminRequest(Request $request): bool
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!$this->jwtService || !$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }

        $claims = $this->jwtService->validateToken(substr($authHeader, 7));
        if (!$claims) {
            return false;
        }

        $roleId = \App\Infrastructure\Auth\UserSession::resolveRoleId($claims);
        return $roleId !== '' && in_array(strtolower($roleId), ['admin', 'administrator'], true);
    }

}
