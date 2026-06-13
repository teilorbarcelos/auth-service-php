<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Helpers\IpHelper;
use App\Infrastructure\Log\RequestIdProcessor;
use App\Infrastructure\Metrics\MetricService;
use Monolog\LogRecord;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Log\LoggerInterface;

class LogMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private MetricService $metricService,
        private RequestIdProcessor $requestIdProcessor
    ) {}

    public function process(Request $request, Handler $handler): Response
    {
        $start = microtime(true);
        $requestId = bin2hex(random_bytes(8));

        $this->requestIdProcessor->setRequestId($requestId);

        $response = null;
        try {
            $response = $handler->handle($request);
        } finally {
            $this->requestIdProcessor->resetRequestId();
            $duration = microtime(true) - $start;
            $durationMs = round($duration * 1000, 2);

            $method = $request->getMethod();
            $path = $request->getUri()->getPath();
            $status = $response instanceof Response ? $response->getStatusCode() : 500;

            $this->logger->info('Request processed', [
                'method' => $method,
                'url' => (string) $request->getUri(),
                'status' => $status,
                'duration_ms' => $durationMs,
                'ip' => IpHelper::getClientIp($request),
                'user_agent' => $request->getHeaderLine('User-Agent'),
            ]);

            $this->metricService->incrementCounter('http_requests_total',
                ['method', 'status', 'path'],
                [$method, (string)$status, $path]
            );

            $this->metricService->recordTimer('http_request_duration_ms', $durationMs,
                ['method', 'path'],
                [$method, $path]
            );
        }

        return $response->withHeader('X-Request-ID', $requestId);
    }

}
