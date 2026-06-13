<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Middleware\JsonErrorMiddleware;
use Slim\Exception\HttpBadRequestException;
use Slim\Psr7\Response;
use Tests\WebTestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class JsonErrorMiddlewareTest extends WebTestCase
{
    private JsonErrorMiddleware $middleware;
    /** @var \PHPUnit\Framework\MockObject\MockObject&ServerRequestInterface */
    private $requestMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&RequestHandlerInterface */
    private $handlerMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new JsonErrorMiddleware();

        $uriMock = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uriMock->method('getPath')->willReturn('/test-path');

        $this->requestMock = $this->createMock(ServerRequestInterface::class);
        $this->requestMock->method('getUri')->willReturn($uriMock);
        $this->requestMock->method('getMethod')->willReturn('GET');

        $this->handlerMock = $this->createMock(RequestHandlerInterface::class);
    }

    public function testProcessReturnsResponseOnSuccess(): void
    {
        $response = new Response();
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->willReturn($response);

        $result = $this->middleware->process($this->requestMock, $this->handlerMock);
        $this->assertSame($response, $result);
    }

    public function testProcessHandlesHttpException(): void
    {
        $exception = new HttpBadRequestException($this->requestMock, 'Bad Request Test');
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $result = $this->middleware->process($this->requestMock, $this->handlerMock);

        $this->assertEquals(400, $result->getStatusCode());
    }

    public function testProcessHandlesValidationException(): void
    {
        $errors = ['field' => 'error message'];
        $exception = new \App\Core\Exceptions\ValidationException($errors);
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $result = $this->middleware->process($this->requestMock, $this->handlerMock);

        $this->assertEquals(400, $result->getStatusCode());
    }

    public function testProcessHandlesGenericThrowable(): void
    {
        $exception = new \Exception('Unexpected Error', 501);
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $result = $this->middleware->process($this->requestMock, $this->handlerMock);

        $this->assertEquals(501, $result->getStatusCode());
    }

    public function testProcessHandlesDebugTrace(): void
    {
        $_ENV['APP_DEBUG'] = 'true';
        $exception = new \Exception('Debug Error');
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $result = $this->middleware->process($this->requestMock, $this->handlerMock);

        $body = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('trace', $body['error']);

        $_ENV['APP_DEBUG'] = 'false';
    }
}
