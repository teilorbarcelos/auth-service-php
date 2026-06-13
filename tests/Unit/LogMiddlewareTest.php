<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Log\RequestIdProcessor;
use App\Infrastructure\Metrics\MetricService;
use App\Middleware\LogMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class LogMiddlewareTest extends TestCase
{
    public function testRequestIdIsResetAfterRequest(): void
    {
        $processor = new RequestIdProcessor();
        $middleware = new LogMiddleware(
            new NullLogger(),
            $this->createMock(MetricService::class),
            $processor
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn(new \Slim\Psr7\Uri('http', 'localhost', null, '/test'));
        $request->method('getHeaderLine')->willReturn('');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('withHeader')->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $this->assertNull($this->readRequestId($processor));

        $middleware->process($request, $handler);

        $this->assertNull($this->readRequestId($processor));
    }

    public function testRequestIdIsResetEvenWhenHandlerThrows(): void
    {
        $processor = new RequestIdProcessor();
        $middleware = new LogMiddleware(
            new NullLogger(),
            $this->createMock(MetricService::class),
            $processor
        );

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn(new \Slim\Psr7\Uri('http', 'localhost', null, '/test'));
        $request->method('getHeaderLine')->willReturn('');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException(new \RuntimeException('boom'));

        try {
            $middleware->process($request, $handler);
            $this->fail('Exception expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertNull($this->readRequestId($processor));
    }

    private function readRequestId(RequestIdProcessor $processor): ?string
    {
        $ref = new \ReflectionClass($processor);
        $prop = $ref->getProperty('requestId');
        $prop->setAccessible(true);
        $value = $prop->getValue($processor);
        return is_string($value) ? $value : null;
    }
}
