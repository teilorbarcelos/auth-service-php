<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = new \Slim\Psr7\Response();
            return $this->addHeaders($response, $request);
        }

        $response = $handler->handle($request);
        return $this->addHeaders($response, $request);
    }

    private function addHeaders(Response $response, Request $request): Response
    {
        $origin = $request->getHeaderLine('Origin') ?: '*';
        $headers = $request->getHeaderLine('Access-Control-Request-Headers') ?: 'Content-Type, Authorization, X-Requested-With';

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', $headers)
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }
}
