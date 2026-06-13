<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

use App\Infrastructure\Auth\UserSession;
use App\Infrastructure\Messaging\RabbitMQProvider;
use App\Modules\Audit\ErrorLog;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class ErrorAuditService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?RabbitMQProvider $messaging = null,
    ) {
    }

    /** @param array<string, mixed> $extraData */
    public function auditError(ServerRequestInterface $request, \Throwable $exception, string $source, array $extraData = []): void
    {
        $userId = $request->getAttribute('userId') ?? UserSession::getInstance()->getUserId();
        if ($userId && !\Illuminate\Support\Str::isUuid($userId)) {
            $userId = null;
        }

        if (!$userId) {
            return;
        }

        try {
            $errorData = array_merge([
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
                'params' => $request->getQueryParams(),
                'body' => $request->getParsedBody()
            ], $extraData);

            $this->logger->error("API Error: [{$source}] " . $exception->getMessage(), [
                'source' => $source,
                'user_id' => $userId,
                'error' => $errorData,
                'request_id' => $request->getAttribute('request_id')
            ]);

            if ($this->messaging && ($_ENV['ERROR_LOG_ASYNC'] ?? 'false') === 'true') {
                $this->messaging->publish('error_log', [
                    'id_user' => $userId,
                    'source' => $source . ': ' . $request->getUri()->getPath(),
                    'error_message' => $exception->getMessage(),
                    'error_data' => $errorData,
                ]);
            } else {
                ErrorLog::create([
                    'id_user' => $userId,
                    'source' => $source . ': ' . $request->getUri()->getPath(),
                    'error_message' => $exception->getMessage(),
                    'error_data' => $errorData
                ]);
            }
        } catch (\Exception $e) {
            error_log("Failed to audit error: " . $e->getMessage());
        }
    }
}
