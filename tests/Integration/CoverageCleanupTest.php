<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\WebTestCase;
use App\Infrastructure\Auth\JwtService;

class CoverageCleanupTest extends WebTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $jwtService = $this->getContainer()->get(JwtService::class);
        $tokens = $jwtService->createTokenPair('123', [
            'email' => 'admin@test.com',
            'role' => ['id' => 'administrator', 'name' => 'Administrator'],
            'permissions' => [['feature' => 'user', 'view' => true]]
        ]);
        $this->token = $tokens['token'];
        
        $jwtService->registerTokens('123', [$tokens['token'], $tokens['refreshToken']]);
    }

    public function testBaseControllerListAll(): void
    {
        $response = $this->request('GET', '/v1/user/all', [], ['Authorization' => 'Bearer ' . $this->token]);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBaseControllerDeleteNotFound(): void
    {
        $fakeUuid = '00000000-0000-0000-0000-000000000000';
        $response = $this->request('DELETE', '/v1/user/' . $fakeUuid, [], ['Authorization' => 'Bearer ' . $this->token]);
        $this->assertEquals(404, $response->getStatusCode());
    }
    
    public function testBaseControllerToggleStatusNotFound(): void
    {
        $fakeUuid = '00000000-0000-0000-0000-000000000000';
        $response = $this->request('PATCH', '/v1/user/' . $fakeUuid . '/status', ['active' => false], ['Authorization' => 'Bearer ' . $this->token]);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUserSessionGettersSetters(): void
    {
        $session = \App\Infrastructure\Auth\UserSession::getInstance();
        $session->setUserId('999');
        $this->assertEquals('999', $session->getUserId());
        
        $user = ['uid' => '888', 'role' => ['id' => 'user'], 'id_role' => 'user'];
        $session->setUser($user);
        $this->assertEquals($user, $session->getUser());
        $this->assertEquals('888', $session->getUserId());
        
        $session->setUser(null);
        $this->assertNull($session->getUser());
    }

    public function testHealthControllerErrorLogFailure(): void
    {
        $db = $this->createMock(\Illuminate\Database\Capsule\Manager::class);
        $db->method('getConnection')->willThrowException(new \Exception('DB Failure'));
        
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();
        $redis->method('ping')->willThrowException(new \Exception('Redis Failure'));
        $redis->method('get')->willReturn(null);
        $redis->method('setex')->willReturn(true);
        
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $rabbit = $this->createMock(\App\Infrastructure\Messaging\RabbitMQProvider::class);
        $storage = $this->createMock(\App\Infrastructure\Storage\StorageProvider::class);
        
        $controller = new \App\Modules\Health\HealthController($db, $redis, $rabbit, $storage, $logger);
        
        \Illuminate\Database\Capsule\Manager::schema()->dropIfExists('audit.tb_error_log');
        
        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/health');
        $response = new \Slim\Psr7\Response();
        
        $response = $controller($request, $response);
        
        $this->assertEquals(503, $response->getStatusCode());
    }

    public function testUserSessionIsAdminWithIdRole(): void
    {
        $session = \App\Infrastructure\Auth\UserSession::getInstance();
        $session->setUser([
            'uid' => '123',
            'id_role' => 'administrator',
            'permissions' => []
        ]);
        $this->assertTrue($session->isAdmin());

        $session->setUser([
            'uid' => '123',
            'id_role' => 'administrator',
            'permissions' => []
        ]);
        $this->assertTrue($session->isAdmin());

        $session->setUser([
            'uid' => '456',
            'id_role' => 'user',
            'permissions' => []
        ]);
        $this->assertFalse($session->isAdmin());

        $session->setUser(null);
    }

    public function testAuthServiceRequestPasswordResetUserNotFound(): void
    {
        $authService = $this->getContainer()->get(\App\Modules\Auth\AuthService::class);
        $authService->requestPasswordReset('nonexistent@test.com');
        $this->assertTrue(true);
    }

    public function testJwtServiceValidateTokenInvalidFormat(): void
    {
        $jwtService = $this->getContainer()->get(JwtService::class);
        $this->assertNull($jwtService->validateToken('malformed.token.here'));
    }

    public function testJwtServiceProductionSecretCheck(): void
    {
        $originalEnv = getenv('APP_ENV');
        $originalSecret = getenv('JWT_SECRET');
        
        try {
            $_ENV['APP_ENV'] = 'production';
            $_ENV['JWT_SECRET'] = 'default-secret-key-at-least-32-chars-long';
            
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('Segurança Crítica');
            
            $redis = $this->createMock(\Redis::class);
            new JwtService($redis);
        } finally {
            $_ENV['APP_ENV'] = $originalEnv ?: 'testing';
            $_ENV['JWT_SECRET'] = $originalSecret ?: '';
        }
    }

    public function testAuthServiceGetFormattedPermissionsNoRole(): void
    {
        $user = new \App\Modules\User\User();
        $authService = $this->getContainer()->get(\App\Modules\Auth\AuthService::class);
        $reflection = new \ReflectionClass($authService);
        $method = $reflection->getMethod('getFormattedPermissions');
        $method->setAccessible(true);
        $result = $method->invoke($authService, $user);
        $this->assertEquals([], $result);
    }

    public function testAuthServiceValidateResetTokenUserNotFound(): void
    {
        $authService = $this->getContainer()->get(\App\Modules\Auth\AuthService::class);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User not found');
        $authService->validateResetToken('nonexistent@test.com', '123456');
    }

    public function testJwtServiceForceInvalidToken(): void
    {
        $jwtService = $this->getContainer()->get(JwtService::class);
        JwtService::$forceInvalidToken = true;
        $this->assertNull($jwtService->validateToken('any-token'));
        JwtService::$forceInvalidToken = false;
        $this->assertNull($jwtService->validateToken(''));
    }

    public function testAuthServiceGetFormattedPermissionsInvalidPerms(): void
    {
        $authService = $this->getContainer()->get(\App\Modules\Auth\AuthService::class);
        $reflection = new \ReflectionClass($authService);
        $method = $reflection->getMethod('getFormattedPermissions');
        $method->setAccessible(true);

        $user = new \App\Modules\User\User();
        $role = new \App\Modules\Role\Role();
        $feature = new \App\Modules\Feature\Feature();
        $feature->id = 'test';
        $feature->pivot = new \stdClass();
        $feature->pivot->permissions = 'invalid-json'; // This will make json_decode return null
        
        $role->features = new \Illuminate\Database\Eloquent\Collection([$feature]);
        $user->role = $role;

        $result = $method->invoke($authService, $user);
        $this->assertEquals([
            [
                'feature' => 'test',
                'create' => false,
                'view' => false,
                'delete' => false,
                'activate' => false
            ]
        ], $result);
    }

    public function testDatabaseProvider(): void
    {
        \App\Infrastructure\Database\DatabaseProvider::resetInstance();
        $provider = \App\Infrastructure\Database\DatabaseProvider::getInstance();
        $this->assertInstanceOf(\App\Infrastructure\Database\DatabaseProvider::class, $provider);
        $this->assertInstanceOf(\Illuminate\Database\Capsule\Manager::class, $provider->getCapsule());
        $this->assertInstanceOf(\Illuminate\Events\Dispatcher::class, $provider->getDispatcher());

        \App\Infrastructure\Database\DatabaseProvider::resetInstance();
        $newProvider = \App\Infrastructure\Database\DatabaseProvider::getInstance();
        $this->assertNotSame($provider, $newProvider);
    }

    public function testHealthReadyCacheHit(): void
    {
        $originalEnv = getenv('APP_ENV');

        try {
            $_ENV['APP_ENV'] = 'production';
            $db = $this->createMock(\Illuminate\Database\Capsule\Manager::class);
            $redis = $this->getMockBuilder(\Redis::class)
                ->disableOriginalConstructor()
                                ->getMock();

            $cachedData = json_encode([
                'status' => 'UP',
                'timestamp' => '2026-01-01 00:00:00',
                'http_status' => 200,
            ]);
            $redis->method('get')->willReturn($cachedData);

            $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
            $rabbit = $this->createMock(\App\Infrastructure\Messaging\RabbitMQProvider::class);
            $storage = $this->createMock(\App\Infrastructure\Storage\StorageProvider::class);

            $controller = new \App\Modules\Health\HealthController($db, $redis, $rabbit, $storage, $logger);
            $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/health/ready');
            $response = new \Slim\Psr7\Response();

            $response = $controller->ready($request, $response);
            $this->assertEquals(200, $response->getStatusCode());
            $body = json_decode((string)$response->getBody(), true);
            $this->assertEquals('UP', $body['status']);
        } finally {
            $_ENV['APP_ENV'] = $originalEnv ?: 'testing';
        }
    }

    public function testHealthReadyCacheHitWithMissingHttpStatus(): void
    {
        $originalEnv = getenv('APP_ENV');

        try {
            $_ENV['APP_ENV'] = 'production';
            $db = $this->createMock(\Illuminate\Database\Capsule\Manager::class);
            $redis = $this->getMockBuilder(\Redis::class)
                ->disableOriginalConstructor()
                                ->getMock();

            $redis->method('get')->willReturn(json_encode(['status' => 'UP']));

            $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
            $rabbit = $this->createMock(\App\Infrastructure\Messaging\RabbitMQProvider::class);
            $storage = $this->createMock(\App\Infrastructure\Storage\StorageProvider::class);

            $controller = new \App\Modules\Health\HealthController($db, $redis, $rabbit, $storage, $logger);
            $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/health');
            $response = new \Slim\Psr7\Response();

            $response = $controller->ready($request, $response);
            $this->assertEquals(200, $response->getStatusCode());
        } finally {
            $_ENV['APP_ENV'] = $originalEnv ?: 'testing';
        }
    }

    public function testHealthReadyCacheMissInProduction(): void
    {
        $originalEnv = getenv('APP_ENV');

        try {
            $_ENV['APP_ENV'] = 'production';
            $_ENV['MESSAGING_ENABLED'] = 'false';
            $pdo = $this->createMock(\PDO::class);
            $stmt = $this->createMock(\PDOStatement::class);
            $pdo->method('query')->willReturn($stmt);
            $conn = $this->createMock(\Illuminate\Database\Connection::class);
            $conn->method('getPdo')->willReturn($pdo);
            $db = $this->createMock(\Illuminate\Database\Capsule\Manager::class);
            $db->method('getConnection')->willReturn($conn);

            $redis = $this->getMockBuilder(\Redis::class)
                ->disableOriginalConstructor()
                                ->getMock();

            $redis->method('get')->willReturn(null);
            $redis->method('ping')->willReturn('PONG');
            $redis->expects($this->once())->method('setex');

            $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
            $rabbit = $this->createMock(\App\Infrastructure\Messaging\RabbitMQProvider::class);
            $storage = $this->createMock(\App\Infrastructure\Storage\StorageProvider::class);
            $storage->method('exists')->willReturn(true);

            $controller = new \App\Modules\Health\HealthController($db, $redis, $rabbit, $storage, $logger);
            $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/health/ready');
            $response = new \Slim\Psr7\Response();

            $response = $controller->ready($request, $response);
            $this->assertEquals(200, $response->getStatusCode());
            $body = json_decode((string)$response->getBody(), true);
            $this->assertArrayHasKey('checks', $body);
            $this->assertEquals('UP', $body['status']);
        } finally {
            $_ENV['APP_ENV'] = $originalEnv ?: 'testing';
        }
    }

    public function testErrorAuditServiceInvalidUserId(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $service = new \App\Infrastructure\Audit\ErrorAuditService($logger);
        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn('not-a-uuid');
        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn('/test');
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getQueryParams')->willReturn([]);
        $request->method('getParsedBody')->willReturn([]);

        $service->auditError($request, new \Exception('test'), 'SOURCE');
        $this->assertTrue(true);
    }

    public function testErrorAuditServiceNoUserId(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $service = new \App\Infrastructure\Audit\ErrorAuditService($logger);
        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn(null);
        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn('/test');
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getQueryParams')->willReturn([]);
        $request->method('getParsedBody')->willReturn([]);

        $service->auditError($request, new \Exception('test'), 'SOURCE');
        $this->assertTrue(true);
    }

    public function testErrorAuditServiceWithMessaging(): void
    {
        $originalEnv = $_ENV['ERROR_LOG_ASYNC'] ?? 'false';
        $_ENV['ERROR_LOG_ASYNC'] = 'true';

        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $messaging = $this->createMock(\App\Infrastructure\Messaging\RabbitMQProvider::class);
        $messaging->expects($this->once())->method('publish');

        $service = new \App\Infrastructure\Audit\ErrorAuditService($logger, $messaging);
        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn('123e4567-e89b-12d3-a456-426614174000');
        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn('/test');
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getQueryParams')->willReturn([]);
        $request->method('getParsedBody')->willReturn([]);

        $service->auditError($request, new \Exception('test msg'), 'SOURCE');
        $_ENV['ERROR_LOG_ASYNC'] = $originalEnv;
    }

    public function testErrorAuditServiceCatchBlock(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->method('error')->willThrowException(new \Exception('log failure'));

        $service = new \App\Infrastructure\Audit\ErrorAuditService($logger);
        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn('123e4567-e89b-12d3-a456-426614174000');
        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn('/test');
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getQueryParams')->willReturn([]);
        $request->method('getParsedBody')->willReturn([]);

        $service->auditError($request, new \Exception('catch-test'), 'SOURCE');
        $this->assertTrue(true);
    }

    public function testRateLimitByUser(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        $redis->method('incr')->willReturn(1);
        $redis->method('expire')->willReturn(true);

        $middleware = new \App\Middleware\RateLimitMiddleware($redis);

        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn('user-123');
        $request->method('getUri')->willReturn(
            $this->createMock(\Psr\Http\Message\UriInterface::class)
        );
        $request->method('getHeaderLine')->willReturn('');

        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new \Slim\Psr7\Response());

        $response = $middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAuthServiceRoleCacheHit(): void
    {
        $redis = $this->createMock(\Redis::class);
        $cachedPermissions = json_encode([
            ['feature' => 'user', 'view' => true, 'create' => false, 'delete' => false, 'activate' => false]
        ]);
        $redis->method('get')->willReturn($cachedPermissions);

        $jwtMock = $this->createMock(\App\Infrastructure\Auth\JwtService::class);
        $emailMock = $this->createMock(\App\Infrastructure\Email\EmailProvider::class);

        $authService = new \App\Modules\Auth\AuthService($jwtMock, $emailMock, $redis);

        $reflection = new \ReflectionClass($authService);
        $method = $reflection->getMethod('getFormattedPermissions');
        $method->setAccessible(true);

        $role = new \App\Modules\Role\Role();
        $role->id = 'admin';
        $user = new \App\Modules\User\User();
        $user->role = $role;

        $result = $method->invoke($authService, $user);
        $this->assertCount(1, $result);
        $this->assertEquals('user', $result[0]['feature']);
    }

    public function testAuthServiceRoleCacheMiss(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('get')->willReturn(false);
        $redis->expects($this->once())->method('setex');

        $jwtMock = $this->createMock(\App\Infrastructure\Auth\JwtService::class);
        $emailMock = $this->createMock(\App\Infrastructure\Email\EmailProvider::class);

        $authService = new \App\Modules\Auth\AuthService($jwtMock, $emailMock, $redis);

        $reflection = new \ReflectionClass($authService);
        $method = $reflection->getMethod('getFormattedPermissions');
        $method->setAccessible(true);

        $role = new \App\Modules\Role\Role();
        $role->id = 'admin';
        $role->setRelation('features', new \Illuminate\Database\Eloquent\Collection([]));
        $user = new \App\Modules\User\User();
        $user->role = $role;

        $result = $method->invoke($authService, $user);
        $this->assertEquals([], $result);
    }

    public function testMetricsAuthWithToken(): void
    {
        $originalToken = $_ENV['METRICS_TOKEN'] ?? '';
        $_ENV['METRICS_TOKEN'] = 'secret123';

        $metricService = $this->getContainer()->get(\App\Infrastructure\Metrics\MetricService::class);
        $controller = new \App\Modules\Metrics\MetricsController($metricService);

        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Authorization')->willReturn('');
        $response = new \Slim\Psr7\Response();

        $response = $controller($request, $response);
        $this->assertEquals(403, $response->getStatusCode());

        $request2 = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request2->method('getHeaderLine')->with('Authorization')->willReturn('Bearer secret123');
        $response2 = new \Slim\Psr7\Response();

        $response2 = $controller($request2, $response2);
        $this->assertEquals(200, $response2->getStatusCode());

        $_ENV['METRICS_TOKEN'] = $originalToken;
    }

    public function testLiveEndpoint(): void
    {
        $db = $this->createMock(\Illuminate\Database\Capsule\Manager::class);
        $redis = $this->createMock(\Redis::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $rabbit = $this->createMock(\App\Infrastructure\Messaging\RabbitMQProvider::class);
        $storage = $this->createMock(\App\Infrastructure\Storage\StorageProvider::class);

        $controller = new \App\Modules\Health\HealthController($db, $redis, $rabbit, $storage, $logger);
        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/health/live');
        $response = new \Slim\Psr7\Response();

        $response = $controller->live($request, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('UP', $body['status']);
    }
}
