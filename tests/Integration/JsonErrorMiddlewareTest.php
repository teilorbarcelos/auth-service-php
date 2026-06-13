<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Middleware\JsonErrorMiddleware;
use App\Modules\Audit\ErrorLog;
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
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Infrastructure\Metrics\MetricService */
    private $metricServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $this->metricServiceMock = $this->createMock(\App\Infrastructure\Metrics\MetricService::class);
        $errorAudit = new \App\Infrastructure\Audit\ErrorAuditService($logger);
        $this->middleware = new JsonErrorMiddleware($errorAudit, $this->metricServiceMock);
        
        $uriMock = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uriMock->method('getPath')->willReturn('/test-path');
        
        $this->requestMock = $this->createMock(ServerRequestInterface::class);
        $this->requestMock->method('getUri')->willReturn($uriMock);
        $this->requestMock->method('getMethod')->willReturn('GET');
        $this->requestMock->method('getQueryParams')->willReturn([]);
        $this->requestMock->method('getParsedBody')->willReturn([]);
        $this->requestMock->method('getAttribute')->willReturn('123e4567-e89b-12d3-a456-426614174000');
        
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

    public function testProcessHandlesHttpExceptionAndAudits(): void
    {
        $exception = new HttpBadRequestException($this->requestMock, 'Bad Request Test');
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $this->metricServiceMock->expects($this->once())
            ->method('incrementCounter')
            ->with('exceptions_total', ['type'], ['HttpBadRequestException']);

        $result = $this->middleware->process($this->requestMock, $this->handlerMock);

        $this->assertEquals(400, $result->getStatusCode());
        
        // Verify audit log entry
        $log = ErrorLog::where('error_message', 'Bad Request Test')->first();
        $this->assertNotNull($log);
        $this->assertStringStartsWith('HTTP_ERROR', $log->source);
    }

    public function testProcessHandlesValidationExceptionAndAudits(): void
    {
        $errors = ['field' => 'error message'];
        $exception = new \App\Core\Exceptions\ValidationException($errors);
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $this->metricServiceMock->expects($this->once())
            ->method('incrementCounter')
            ->with('exceptions_total', ['type'], ['ValidationException']);

        $result = $this->middleware->process($this->requestMock, $this->handlerMock);

        $this->assertEquals(400, $result->getStatusCode());
        
        // Verify audit log entry
        $log = ErrorLog::where('error_message', 'Validation Failed')->first();
        $this->assertNotNull($log);
        $this->assertStringStartsWith('VALIDATION_ERROR', $log->source);
        $this->assertEquals($errors, $log->error_data['validation_errors']);
    }

    public function testProcessHandlesGenericThrowableAndAudits(): void
    {
        $exception = new \Exception('Unexpected Error', 501);
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $this->metricServiceMock->expects($this->once())
            ->method('incrementCounter')
            ->with('exceptions_total', ['type'], ['Exception']);

        $result = $this->middleware->process($this->requestMock, $this->handlerMock);

        $this->assertEquals(501, $result->getStatusCode());
        
        // Verify audit log entry
        $log = ErrorLog::where('error_message', 'Unexpected Error')->first();
        $this->assertNotNull($log);
        $this->assertStringStartsWith('SERVER_ERROR', $log->source);
    }

    public function testProcessHandlesDebugTrace(): void
    {
        $_ENV['APP_DEBUG'] = 'true';
        $exception = new \Exception('Debug Error');
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $this->metricServiceMock->expects($this->once())
            ->method('incrementCounter')
            ->with('exceptions_total', ['type'], ['Exception']);

        $result = $this->middleware->process($this->requestMock, $this->handlerMock);
        
        /** @var array{success: bool, error: array{trace: array<mixed>}} $body */
        $body = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('trace', $body['error']);

        $_ENV['APP_DEBUG'] = 'false';
    }
}
