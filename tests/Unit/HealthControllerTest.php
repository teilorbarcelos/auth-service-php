<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Health\HealthController;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

class HealthControllerTest extends TestCase
{
    private HealthController $controller;
    /** @var \PHPUnit\Framework\MockObject\MockObject&Capsule */
    private $dbMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\Redis */
    private $redisMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&LoggerInterface */
    private $loggerMock;

    protected function setUp(): void
    {
        $this->dbMock = $this->getMockBuilder(Capsule::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection'])
            ->getMock();
        $this->redisMock = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->controller = new HealthController(
            $this->dbMock,
            $this->redisMock,
            $this->loggerMock
        );
    }

    private function createRequest(): ServerRequestInterface
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/health/live');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    public function testLive(): void
    {
        $request = $this->createRequest();
        $response = new Response();

        $result = $this->controller->live($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('UP', $body['status']);
        $this->assertArrayHasKey('timestamp', $body);
        $this->assertEquals('/health/live', $body['path']);
    }

    public function testReadyWithAllChecksPassing(): void
    {
        $connectionMock = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPdo'])
            ->getMock();

        $pdoMock = $this->createMock(\PDO::class);
        $pdoMock->expects($this->once())->method('query')->with('SELECT 1');
        $connectionMock->method('getPdo')->willReturn($pdoMock);

        $this->dbMock->method('getConnection')->willReturn($connectionMock);
        $this->redisMock->method('ping')->willReturn(true);

        $request = $this->createRequest();
        $response = new Response();

        $result = $this->controller->ready($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('UP', $body['status']);
        $this->assertEquals('OK', $body['checks']['database']['status']);
        $this->assertEquals('OK', $body['checks']['redis']['status']);
    }

    public function testReadyWithDatabaseFailure(): void
    {
        $connectionMock = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPdo'])
            ->getMock();
        $connectionMock->method('getPdo')->willThrowException(new \PDOException('Connection refused'));

        $this->dbMock->method('getConnection')->willReturn($connectionMock);
        $this->redisMock->method('ping')->willReturn(true);

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('System Health Degraded: database is down'));

        $request = $this->createRequest();
        $response = new Response();

        $result = $this->controller->ready($request, $response);

        $this->assertEquals(503, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('DEGRADED', $body['status']);
        $this->assertEquals('ERROR', $body['checks']['database']['status']);
        $this->assertEquals('OK', $body['checks']['redis']['status']);
    }

    public function testReadyWithRedisFailure(): void
    {
        $connectionMock = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPdo'])
            ->getMock();

        $pdoMock = $this->createMock(\PDO::class);
        $pdoMock->expects($this->once())->method('query')->with('SELECT 1');
        $connectionMock->method('getPdo')->willReturn($pdoMock);

        $this->dbMock->method('getConnection')->willReturn($connectionMock);
        $this->redisMock->method('ping')->willThrowException(new \Exception('Redis down'));

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('System Health Degraded: redis is down'));

        $request = $this->createRequest();
        $response = new Response();

        $result = $this->controller->ready($request, $response);

        $this->assertEquals(503, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('DEGRADED', $body['status']);
        $this->assertEquals('OK', $body['checks']['database']['status']);
        $this->assertEquals('ERROR', $body['checks']['redis']['status']);
    }

    public function testReadyWithEnvDebugAndDeploy(): void
    {
        $_ENV['APP_DEBUG'] = 'true';
        $_ENV['APP_DEPLOY_TIMESTAMP'] = '2024-01-01T00:00:00Z';
        $_ENV['APP_VERSION'] = '2.0.0';

        $connectionMock = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPdo'])
            ->getMock();

        $pdoMock = $this->createMock(\PDO::class);
        $pdoMock->expects($this->once())->method('query')->with('SELECT 1');
        $connectionMock->method('getPdo')->willReturn($pdoMock);

        $this->dbMock->method('getConnection')->willReturn($connectionMock);
        $this->redisMock->method('ping')->willReturn(true);

        $request = $this->createRequest();
        $response = new Response();

        $result = $this->controller->ready($request, $response);

        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('UP', $body['status']);
        $this->assertEquals('2024-01-01T00:00:00Z', $body['deploy']['timestamp']);
        $this->assertEquals('2.0.0', $body['deploy']['version']);
        $this->assertArrayHasKey('uptime', $body);

        $_ENV['APP_DEBUG'] = 'false';
        unset($_ENV['APP_DEPLOY_TIMESTAMP']);
        unset($_ENV['APP_VERSION']);
    }
}
