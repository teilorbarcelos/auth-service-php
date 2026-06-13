<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

class BodySizeLimitMiddleware implements MiddlewareInterface
{
    private int $maxBytes;

    public function __construct(?int $maxBytes = null)
    {
        $envValue = $_ENV['REQUEST_BODY_MAX_SIZE'] ?? '2M';
        $this->maxBytes = $maxBytes ?? $this->parseSize($envValue);
    }

    public function process(Request $request, Handler $handler): Response
    {
        $contentLength = $request->getHeaderLine('Content-Length');
        if ($contentLength !== '' && ctype_digit($contentLength)) {
            $size = (int) $contentLength;
            if ($size > $this->maxBytes) {
                return $this->reject();
            }
        }

        return $handler->handle($request);
    }

    private function parseSize(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }

    private function reject(): Response
    {
        $response = new SlimResponse();
        $payload = json_encode([
            'success' => false,
            'error' => [
                'message' => "Request body too large. Max {$this->maxBytes} bytes allowed.",
                'code' => 'PAYLOAD_TOO_LARGE',
            ],
        ]);
        $response->getBody()->write($payload ?: '{}');
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(413);
    }
}
