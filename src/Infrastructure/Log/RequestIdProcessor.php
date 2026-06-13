<?php

declare(strict_types=1);

namespace App\Infrastructure\Log;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RequestIdProcessor implements ProcessorInterface
{
    private ?string $requestId = null;

    public function __construct(?string $requestId = null)
    {
        $this->requestId = $requestId;
    }

    public function setRequestId(string $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function resetRequestId(): void
    {
        $this->requestId = null;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($this->requestId !== null) {
            $record->extra['request_id'] = $this->requestId;
        }

        return $record;
    }
}
