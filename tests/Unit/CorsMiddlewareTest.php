<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\CorsMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class CorsMiddlewareTest extends TestCase
{
    private CorsMiddleware $middleware;
    /** @var \PHPUnit\Framework\MockObject\MockObject&ServerRequestInterface */
    private $requestMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&RequestHandlerInterface */
    private $handlerMock;

    protected function setUp(): void
    {
        $this->middleware = new CorsMiddleware();
        $this->requestMock = $this->createMock(ServerRequestInterface::class);
        $this->handlerMock = $this->createMock(RequestHandlerInterface::class);
    }

    public function testProcessRegularRequest(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getMethod')
            ->willReturn('GET');

        $this->requestMock->expects($this->exactly(2))
            ->method('getHeaderLine')
            ->willReturnMap([
                ['Origin', 'http://localhost:3000'],
                ['Access-Control-Request-Headers', 'X-Custom-Header']
            ]);

        $response = new Response();
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->willReturn($response);

        $result = $this->middleware->process($this->requestMock, $this->handlerMock);

        $this->assertEquals('http://localhost:3000', $result->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertEquals('X-Custom-Header', $result->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertEquals('true', $result->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    public function testProcessOptionsRequest(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getMethod')
            ->willReturn('OPTIONS');

        $this->requestMock->expects($this->exactly(2))
            ->method('getHeaderLine')
            ->willReturnMap([
                ['Origin', ''],
                ['Access-Control-Request-Headers', '']
            ]);

        $this->handlerMock->expects($this->never())
            ->method('handle');

        $result = $this->middleware->process($this->requestMock, $this->handlerMock);

        $this->assertEquals('*', $result->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('Authorization', $result->getHeaderLine('Access-Control-Allow-Headers'));
    }
}
