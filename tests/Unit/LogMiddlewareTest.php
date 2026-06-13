<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Helpers\IpHelper;
use App\Infrastructure\Log\RequestIdProcessor;
use App\Middleware\LogMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class LogMiddlewareTest extends TestCase
{
    public function testProcessSetsRequestIdAndLogs(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $requestIdProcessor = new RequestIdProcessor();

        $middleware = new LogMiddleware($logger, $requestIdProcessor);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/test');
        $uri->method('__toString')->willReturn('http://localhost/test');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaderLine')->with('User-Agent')->willReturn('test-agent');
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $logger->expects($this->once())
            ->method('info')
            ->with('Request processed', $this->callback(function ($context) {
                return isset($context['method'], $context['url'], $context['status'], $context['duration_ms'], $context['ip'], $context['user_agent'])
                    && $context['method'] === 'GET'
                    && $context['status'] === 200
                    && $context['user_agent'] === 'test-agent';
            }));

        $response->expects($this->once())
            ->method('withHeader')
            ->with('X-Request-ID', $this->isType('string'))
            ->willReturn($response);

        $result = $middleware->process($request, $handler);
        $this->assertSame($response, $result);
    }

    public function testProcessHandlesExceptionAndResetsRequestId(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $requestIdProcessor = new RequestIdProcessor();
        $requestIdProcessor->setRequestId('before');

        $middleware = new LogMiddleware($logger, $requestIdProcessor);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/error');
        $uri->method('__toString')->willReturn('http://localhost/error');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaderLine')->with('User-Agent')->willReturn('');
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willThrowException(new \RuntimeException('fail'));

        $logger->expects($this->once())
            ->method('info')
            ->with('Request processed', $this->callback(function ($context) {
                return $context['status'] === 500;
            }));

        $this->expectException(\RuntimeException::class);
        $middleware->process($request, $handler);
    }
}
