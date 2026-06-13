<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\WebTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class InfrastructureTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        $_ENV['MESSAGING_ENABLED'] = 'false';
    }

    public function testHealthEndpoint(): void

    {
        $response = $this->request('GET', '/health');
        
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertEquals('UP', $body['status']);
        $this->assertArrayHasKey('uptime', $body);
        $this->assertEquals('OK', $body['checks']['database']['status']);
        $this->assertEquals('OK', $body['checks']['redis']['status']);
    }

    public function testRateLimitTrigger(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->method('incr')->willReturn(100); // Higher than limit
        
        $middleware = new \App\Middleware\RateLimitMiddleware($redis);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/test');
        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        
        $response = $middleware->process($request, $handler);
        
        $this->assertEquals(429, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Too Many Requests', $body['error']);
    }

    public function testHealthEndpointDegraded(): void
    {
        // Mock DB to fail
        $db = $this->createMock(\Illuminate\Database\Capsule\Manager::class);
        $db->method('getConnection')->willThrowException(new \Exception('DB Error'));
        
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();
            
        $redis->method('ping')->willThrowException(new \Exception('Redis Error'));
        
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $rabbit = $this->createMock(\App\Infrastructure\Messaging\RabbitMQProvider::class);
        $storage = $this->createMock(\App\Infrastructure\Storage\StorageProvider::class);
        $controller = new \App\Modules\Health\HealthController($db, $redis, $rabbit, $storage, $logger);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');
        $response = new \Slim\Psr7\Response();
        
        $response = $controller($request, $response);
        
        $this->assertEquals(503, $response->getStatusCode());
        
        // Verify that it was saved in database (ErrorLog)
        $log = \App\Modules\Audit\ErrorLog::where('error_message', 'DB Error')->first();
        $this->assertNotNull($log);
        $this->assertEquals('DEGRADED', $log->source);
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('DEGRADED', $body['status']);
        $this->assertEquals('ERROR', $body['checks']['database']['status']);
        $this->assertEquals('ERROR', $body['checks']['redis']['status']);
    }

    public function testRateLimitWithForwardedIp(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();
        
        $middleware = new \App\Middleware\RateLimitMiddleware($redis);
        
        // Test HTTP_X_FORWARDED_FOR
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/test', [
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4'
        ]);
        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new \Slim\Psr7\Response());
        
        $response = $middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRateLimitWithUnknownIp(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();
        
        $middleware = new \App\Middleware\RateLimitMiddleware($redis);
        
        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn([]);
        $request->method('getUri')->willReturn(new \Slim\Psr7\Uri('http', 'localhost', 8888, '/v1/test'));
        $request->method('getMethod')->willReturn('GET');
        
        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new \Slim\Psr7\Response());
        
        $response = $middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHealthUptimeUnknown(): void
    {
        $db = $this->createMock(\Illuminate\Database\Capsule\Manager::class);
        $redis = $this->createMock(\Redis::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $rabbit = $this->createMock(\App\Infrastructure\Messaging\RabbitMQProvider::class);
        $storage = $this->createMock(\App\Infrastructure\Storage\StorageProvider::class);
        $controller = new \App\Modules\Health\HealthController($db, $redis, $rabbit, $storage, $logger);
        
        // Use Reflection to test the 'Unknown' branch of getUptime
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getUptime');
        
        // Pass 'Windows' to trigger the 'Unknown' branch
        $uptime = $method->invoke($controller, 'Windows');
        $this->assertEquals('Unknown', $uptime);
    }

    public function testRateLimitIncrement(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();
        
        $redis->method('incr')->willReturn(11);
        $redis->expects($this->never())->method('expire');
        
        $middleware = new \App\Middleware\RateLimitMiddleware($redis);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/test');
        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new \Slim\Psr7\Response());
        
        $response = $middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('49', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testRateLimitAdminBypass(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();
        $redis->expects($this->never())->method('incr');
        
        $middleware = new \App\Middleware\RateLimitMiddleware($redis);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/test');
        $request = $request->withAttribute('user', [
            'uid' => '123',
            'role' => ['id' => 'administrator'],
        ]);
        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new \Slim\Psr7\Response());
        
        $response = $middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-RateLimit-Limit'));
    }

    public function testRateLimitAdminBypassWithJwt(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();
        
        $jwtService = $this->getContainer()->get(\App\Infrastructure\Auth\JwtService::class);
        $middleware = new \App\Middleware\RateLimitMiddleware($redis, $jwtService);
        
        // 1. Valid Admin Token (roleId string)
        $userId = (string) \Illuminate\Support\Str::uuid();
        $tokens = $jwtService->createTokenPair($userId, [
            'email' => 'admin-by@example.com',
            'roleId' => 'administrator',
            'permissions' => []
        ]);
        $jwtService->registerTokens($userId, [$tokens['token']]);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/test')
            ->withHeader('Authorization', 'Bearer ' . $tokens['token']);
        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new \Slim\Psr7\Response());
        
        $response = $middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-RateLimit-Limit'));

        // 2. Valid Admin Token (role array)
        $tokens2 = $jwtService->createTokenPair($userId, [
            'email' => 'admin-by2@example.com',
            'role' => ['id' => 'admin'],
            'permissions' => []
        ]);
        $jwtService->registerTokens($userId, [$tokens2['token']]);
        $request2 = (new ServerRequestFactory())->createServerRequest('GET', '/v1/test')
            ->withHeader('Authorization', 'Bearer ' . $tokens2['token']);
        $response2 = $middleware->process($request2, $handler);
        $this->assertEquals(200, $response2->getStatusCode());

        // 3. Valid Admin Token (role value fallback)
        $tokens3 = $jwtService->createTokenPair($userId, [
            'email' => 'admin-by3@example.com',
            'role' => 'admin',
            'permissions' => []
        ]);
        $jwtService->registerTokens($userId, [$tokens3['token']]);
        $request3 = (new ServerRequestFactory())->createServerRequest('GET', '/v1/test')
            ->withHeader('Authorization', 'Bearer ' . $tokens3['token']);
        $response3 = $middleware->process($request3, $handler);
        $this->assertEquals(200, $response3->getStatusCode());

        // 4. Invalid Token (Trigger validation exception)
        $requestInvalid = (new ServerRequestFactory())->createServerRequest('GET', '/v1/test')
            ->withHeader('Authorization', 'Bearer invalid-token');
        
        $redisMockForInvalid = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();
        $redisMockForInvalid->method('incr')->willReturn(11);
        $middlewareForInvalid = new \App\Middleware\RateLimitMiddleware($redisMockForInvalid, $jwtService);
        
        $responseInvalid = $middlewareForInvalid->process($requestInvalid, $handler);
        $this->assertEquals(200, $responseInvalid->getStatusCode());

        // 5. Valid token but force invalid (validateToken returns null)
        \App\Infrastructure\Auth\JwtService::$forceInvalidToken = true;
        $tokens5 = $jwtService->createTokenPair($userId, [
            'email' => 'admin-by4@example.com',
            'roleId' => 'administrator',
            'permissions' => []
        ]);
        $jwtService->registerTokens($userId, [$tokens5['token']]);
        $request5 = (new ServerRequestFactory())->createServerRequest('GET', '/v1/test')
            ->withHeader('Authorization', 'Bearer ' . $tokens5['token']);
        
        $response5 = $middleware->process($request5, $handler);
        $this->assertEquals(200, $response5->getStatusCode());
        
        // Reset fallback
        \App\Infrastructure\Auth\JwtService::$forceInvalidToken = false;
    }

    public function testRateLimitHeaders(): void
    {
        // Using a non-existent user to ensure it's NOT an admin
        $response = $this->request('POST', '/v1/auth/login', ['email' => 'non-admin@example.com', 'password' => 'password']);
        
        $this->assertTrue($response->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Remaining'));
        $this->assertTrue($response->hasHeader('X-Request-ID'));
    }
    public function testHealthRabbitMQDisabled(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'false';
        
        $container = $this->getContainer();
        $db = $container->get(\Illuminate\Database\Capsule\Manager::class);
        $redis = $container->get(\Redis::class);
        $logger = $container->get(\Psr\Log\LoggerInterface::class);
        
        $rabbit = $this->createMock(\App\Infrastructure\Messaging\RabbitMQProvider::class);
        $storage = $this->createMock(\App\Infrastructure\Storage\StorageProvider::class);
        $controller = new \App\Modules\Health\HealthController($db, $redis, $rabbit, $storage, $logger);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');
        $response = new \Slim\Psr7\Response();
        
        $response = $controller($request, $response);
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertEquals('DISABLED', $body['checks']['rabbitmq']['status']);
        $this->assertEquals('Messaging is disabled in settings', $body['checks']['rabbitmq']['message']);
    }

    public function testHealthRabbitMQError(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'true';
        
        $container = $this->getContainer();
        $db = $container->get(\Illuminate\Database\Capsule\Manager::class);
        $redis = $container->get(\Redis::class);
        $redis->del(['health:probe']);
        $logger = $container->get(\Psr\Log\LoggerInterface::class);
        
        $rabbit = $this->createMock(\App\Infrastructure\Messaging\RabbitMQProvider::class);
        $rabbit->method('connect')->willThrowException(new \Exception('RabbitMQ Connection Failed'));
        $storage = $container->get(\App\Infrastructure\Storage\StorageProvider::class);
        
        $controller = new \App\Modules\Health\HealthController($db, $redis, $rabbit, $storage, $logger);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');
        $response = new \Slim\Psr7\Response();
        
        $response = $controller($request, $response);
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertEquals('ERROR', $body['checks']['rabbitmq']['status']);
        $this->assertEquals('RabbitMQ Connection Failed', $body['checks']['rabbitmq']['message']);
        $this->assertEquals('DEGRADED', $body['status']);
    }

    public function testHealthRabbitMQSuccess(): void
    {
        $db = $this->getContainer()->get('db');
        $redis = $this->getContainer()->get(\Redis::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        
        $rabbit = $this->createMock(\App\Infrastructure\Messaging\RabbitMQProvider::class);
        $rabbit->expects($this->once())->method('connect');
        $storage = $this->getContainer()->get(\App\Infrastructure\Storage\StorageProvider::class);
        
        $controller = new \App\Modules\Health\HealthController($db, $redis, $rabbit, $storage, $logger);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');
        $response = new \Slim\Psr7\Response();
        
        $_ENV['MESSAGING_ENABLED'] = 'true';
        $response = $controller($request, $response);
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertEquals('OK', $body['checks']['rabbitmq']['status']);
        $this->assertEquals('Connected', $body['checks']['rabbitmq']['message']);
    }

    public function testHealthStorageError(): void
    {
        $container = $this->getContainer();
        $db = $container->get(\Illuminate\Database\Capsule\Manager::class);
        $redis = $container->get(\Redis::class);
        $logger = $container->get(\Psr\Log\LoggerInterface::class);
        $rabbit = $this->createMock(\App\Infrastructure\Messaging\RabbitMQProvider::class);
        
        $storage = $this->createMock(\App\Infrastructure\Storage\StorageProvider::class);
        $storage->method('put')->willThrowException(new \Exception('Storage Write Error'));
        
        $controller = new \App\Modules\Health\HealthController($db, $redis, $rabbit, $storage, $logger);
        
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');
        $response = new \Slim\Psr7\Response();
        
        $response = $controller($request, $response);
        $body = json_decode((string)$response->getBody(), true);
        
        $this->assertEquals('ERROR', $body['checks']['storage']['status']);
        $this->assertEquals('Storage Write Error', $body['checks']['storage']['message']);
    }
}


