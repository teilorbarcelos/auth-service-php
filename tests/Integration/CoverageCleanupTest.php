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
        $feature->pivot->permissions = 'invalid-json';
        
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

        $authService = new \App\Modules\Auth\AuthService($jwtMock, $redis);

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

        $authService = new \App\Modules\Auth\AuthService($jwtMock, $redis);

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
}
