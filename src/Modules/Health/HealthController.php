<?php

declare(strict_types=1);

namespace App\Modules\Health;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use Redis;

class HealthController
{
    private const CONTENT_TYPE = 'application/json';
    private const CACHE_TTL = 5;

    public function __construct(
        private DB $db,
        private Redis $redis,
        private \App\Infrastructure\Messaging\RabbitMQProvider $rabbit,
        private \App\Infrastructure\Storage\StorageProvider $storage,
        private \Psr\Log\LoggerInterface $logger
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $result = $this->ready($request, $response);
        return $result->withHeader('X-Health-Source', 'invoke');
    }

    public function live(Request $request, Response $response): Response
    {
        $data = [
            'status' => 'UP',
            'timestamp' => date('Y-m-d H:i:s'),
            'path' => $request->getUri()->getPath(),
        ];

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($json ?: '{}');
        return $response
            ->withHeader('Content-Type', self::CONTENT_TYPE);
    }

    public function ready(Request $request, Response $response): Response
    {
        $isTesting = ($_ENV['APP_ENV'] ?? '') === 'testing';

        if (!$isTesting) {
            $cached = $this->getCachedResult();
            if ($cached !== null) {
                $cached['path'] = $request->getUri()->getPath();
                $json = json_encode($cached, JSON_UNESCAPED_UNICODE);
                $response->getBody()->write($json ?: '{}');
                /** @var mixed $httpStatus */
                $httpStatus = $cached['http_status'] ?? null;
                return $response
                    ->withHeader('Content-Type', self::CONTENT_TYPE)
                    ->withStatus(is_int($httpStatus) ? $httpStatus : 200);
            }
        }

        $checks = $this->runChecks();
        $result = $this->buildResult($checks);

        if (!$isTesting) {
            $this->redis->setex('health:probe', self::CACHE_TTL, json_encode($result));
        }

        $flags = JSON_UNESCAPED_UNICODE;
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            $flags |= JSON_PRETTY_PRINT;
        }
        $result['path'] = $request->getUri()->getPath();
        $json = json_encode($result, $flags);
        $response->getBody()->write($json ?: '{}');
        /** @var mixed $httpStatus */
        $httpStatus = $result['http_status'] ?? null;
        return $response
            ->withHeader('Content-Type', self::CONTENT_TYPE)
            ->withStatus(is_int($httpStatus) ? $httpStatus : 200);
    }

    /** @return array<string, mixed>|null */
    private function getCachedResult(): ?array
    {
        /** @var mixed $cached */
        $cached = $this->redis->get('health:probe');
        if (!is_string($cached)) {
            return null;
        }
        $decoded = json_decode($cached, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @return array<string, array<string, string>> */
    private function runChecks(): array
    {
        $checks = [];
        $checks['database'] = $this->checkDatabase();
        $checks['redis'] = $this->checkRedis();
        $checks['rabbitmq'] = $this->checkRabbitMQ();
        $checks['storage'] = $this->checkStorage();
        return $checks;
    }

    /** @param array<string, array<string, string>> $checks
     * @return array<string, mixed> */
    private function buildResult(array $checks): array
    {
        $status = 'UP';
        foreach ($checks as $name => $check) {
            if ($check['status'] !== 'OK' && $check['status'] !== 'DISABLED') {
                $status = 'DEGRADED';
                $this->logger->warning("System Health Degraded: {$name} is down", [
                    'check' => $name,
                    'message' => $check['message']
                ]);

                try {
                    \App\Modules\Audit\ErrorLog::create([
                        'source' => 'DEGRADED',
                        'error_message' => $check['message'],
                        'error_data' => [
                            'check' => $name,
                            'message' => $check['message']
                        ]
                    ]);
                } catch (\Exception $e) {
                }
            }
        }

        return [
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
            'deploy' => [
                'timestamp' => $_ENV['APP_DEPLOY_TIMESTAMP'] ?? 'unknown',
                'version' => $_ENV['APP_VERSION'] ?? '1.0.0',
            ],
            'uptime' => $this->getUptime(),
            'checks' => $checks,
            'http_status' => $status === 'UP' ? 200 : 503,
            'message' => 'API is running smoothly. All systems operational.'
        ];
    }

    /** @return array<string, string> */
    private function checkDatabase(): array
    {
        try {
            $this->db->getConnection()->getPdo()->query('SELECT 1');
            return ['status' => 'OK', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    /** @return array<string, string> */
    private function checkRedis(): array
    {
        try {
            $this->redis->ping();
            return ['status' => 'OK', 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    /** @return array<string, string> */
    private function checkRabbitMQ(): array
    {
        if (($_ENV['MESSAGING_ENABLED'] ?? 'false') !== 'true') {
            return ['status' => 'DISABLED', 'message' => 'Messaging is disabled in settings'];
        }

        try {
            $this->rabbit->connect();
            return ['status' => 'OK', 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    /** @return array<string, string> */
    private function checkStorage(): array
    {
        try {
            $testFile = '.health_check_temp';
            $this->storage->put($testFile, 'health-check');

            if (!$this->storage->exists($testFile)) {
                 throw new \RuntimeException("Storage write failed: file not found after put");
            }

            $this->storage->delete($testFile);
            return ['status' => 'OK', 'message' => 'Writable'];
        } catch (\Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    private function getUptime(string $os = PHP_OS_FAMILY): string
    {
        if ($os === 'Linux' && is_readable('/proc/uptime')) {
            $uptime = (int) file_get_contents('/proc/uptime');
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            $minutes = floor(($uptime % 3600) / 60);
            $seconds = $uptime % 60;

            return sprintf('%dd %dh %dm %ds', $days, $hours, $minutes, $seconds);
        }

        return 'Unknown';
    }
}
