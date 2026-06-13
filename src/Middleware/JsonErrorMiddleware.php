<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Infrastructure\Audit\ErrorAuditService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;
use Slim\Psr7\Response;

class JsonErrorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ErrorAuditService $errorAudit,
        private \App\Infrastructure\Metrics\MetricService $metricService,
    ) {
    }

    private const CONTENT_TYPE_JSON = 'application/json';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\App\Core\Exceptions\ValidationException $e) {
            $this->metricService->incrementCounter('exceptions_total', ['type'], [$this->getExceptionType($e)]);
            $this->errorAudit->auditError($request, $e, 'VALIDATION_ERROR', ['validation_errors' => $e->getErrors()]);
            $response = new Response();
            $payload = [
                'success' => false,
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => 'VALIDATION_ERROR',
                    'details' => $e->getErrors()
                ]
            ];
            $response->getBody()->write((string) json_encode($payload));
            $finalResponse = $response->withStatus(400);
        } catch (HttpException $e) {
            $this->metricService->incrementCounter('exceptions_total', ['type'], [$this->getExceptionType($e)]);
            $this->errorAudit->auditError($request, $e, 'HTTP_ERROR');
            $response = new Response();
            if ($e->getCode() === 401) {
                $payload = [
                    'success' => false,
                    'error' => 'UnauthorizedError'
                ];
            } else {
                $payload = [
                    'success' => false,
                    'error' => [
                        'message' => $e->getMessage(),
                        'code' => 'HTTP_ERROR',
                        'details' => null
                    ]
                ];
            }

            $response->getBody()->write((string) json_encode($payload));
            $finalResponse = $response->withStatus($e->getCode());
        } catch (\Throwable $e) {
            $this->metricService->incrementCounter('exceptions_total', ['type'], [$this->getExceptionType($e)]);
            $this->errorAudit->auditError($request, $e, 'SERVER_ERROR');
            $response = new Response();
            $statusCode = 500;
            $code = $e->getCode();
            $codeInt = is_numeric($code) ? (int)$code : 0;
            if ($codeInt >= 400 && $codeInt < 600) {
                $statusCode = $codeInt;
            }

            if ($statusCode === 401 || $code === 401 || $code === '401') {
                $statusCode = 401;
                $payload = [
                    'success' => false,
                    'error' => 'UnauthorizedError'
                ];
            } else {
                $payload = [
                    'success' => false,
                    'error' => [
                        'message' => $e->getMessage() ?: 'Internal Server Error',
                        'code' => 'INTERNAL_SERVER_ERROR',
                        'trace' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? $e->getTrace() : null
                    ]
                ];
            }

            $response->getBody()->write((string) json_encode($payload));
            $finalResponse = $response->withStatus($statusCode);
        }

        return $finalResponse->withHeader('Content-Type', self::CONTENT_TYPE_JSON);
    }

    private function getExceptionType(\Throwable $e): string
    {
        return (new \ReflectionClass($e))->getShortName();
    }
}
