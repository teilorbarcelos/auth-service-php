<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Log\RequestIdProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class RequestIdProcessorTest extends TestCase
{
    public function testRequestIdIsNullByDefault(): void
    {
        $processor = new RequestIdProcessor();
        $record = $this->makeRecord();

        $result = $processor($record);

        $this->assertArrayNotHasKey('request_id', $result->extra);
    }

    public function testSetRequestIdAddsToExtra(): void
    {
        $processor = new RequestIdProcessor();
        $processor->setRequestId('abc123');
        $record = $this->makeRecord();

        $result = $processor($record);

        $this->assertSame('abc123', $result->extra['request_id']);
    }

    public function testResetRequestIdRemovesFromExtra(): void
    {
        $processor = new RequestIdProcessor();
        $processor->setRequestId('abc123');
        $processor->resetRequestId();
        $record = $this->makeRecord();

        $result = $processor($record);

        $this->assertArrayNotHasKey('request_id', $result->extra);
    }

    public function testConstructorWithRequestId(): void
    {
        $processor = new RequestIdProcessor('initial-id');
        $record = $this->makeRecord();

        $result = $processor($record);

        $this->assertSame('initial-id', $result->extra['request_id']);
    }

    private function makeRecord(): LogRecord
    {
        return new LogRecord(
            new \DateTimeImmutable(),
            'test',
            Level::Info,
            'message',
            [],
            []
        );
    }
}
