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

        $role = new \App\Modules\Role\Role();
        $role->id = 'admin';
        $role->setRelation('features', new \Illuminate\Database\Eloquent\Collection([]));
        $user = new \App\Modules\User\User();
        $user->role = $role;

        $result = $method->invoke($authService, $user);
        $this->assertEquals([], $result);
    }

    public function testBaseControllerDebugMode(): void
    {
        $_ENV['APP_DEBUG'] = 'true';

        $serviceMock = $this->createMock(\App\Core\BaseService::class);
        $controller = new class ($serviceMock) extends \App\Core\BaseController {
            public function __construct(\App\Core\BaseService $service) {
                $this->service = $service;
            }
            public function exposeJsonResponse(\Psr\Http\Message\ResponseInterface $response, mixed $data): \Psr\Http\Message\ResponseInterface {
                return $this->jsonResponse($response, $data);
            }
        };

        $response = new \Slim\Psr7\Response();
        $result = $controller->exposeJsonResponse($response, ['test' => true]);
        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertTrue($body['test']);

        $_ENV['APP_DEBUG'] = 'false';
    }

    public function testBaseControllerTransformWithModel(): void
    {
        $transformer = new \App\Core\Transformers\FeatureTransformer();
        $controller = new class extends \App\Core\BaseController {
            public function exposeTransform(mixed $data, ?\App\Core\Transformers\BaseTransformer $t = null): mixed {
                return $this->transformResponse($data, $t);
            }
        };

        $feature = new \App\Modules\Feature\Feature(['id' => 't1', 'name' => 'Test']);
        $result = $controller->exposeTransform($feature, $transformer);
        $this->assertIsArray($result);
        $this->assertEquals('t1', $result['id']);
    }

    public function testBaseControllerTransformWithCollection(): void
    {
        $transformer = new \App\Core\Transformers\FeatureTransformer();
        $controller = new class extends \App\Core\BaseController {
            public function exposeTransform(mixed $data, ?\App\Core\Transformers\BaseTransformer $t = null): mixed {
                return $this->transformResponse($data, $t);
            }
        };

        $collection = new \Illuminate\Database\Eloquent\Collection([
            new \App\Modules\Feature\Feature(['id' => 'c1', 'name' => 'C1']),
            new \App\Modules\Feature\Feature(['id' => 'c2', 'name' => 'C2']),
        ]);
        $result = $controller->exposeTransform($collection, $transformer);
        $this->assertCount(2, $result);
    }

    public function testBaseControllerTransformWithPaginatedArray(): void
    {
        $transformer = new \App\Core\Transformers\FeatureTransformer();
        $controller = new class extends \App\Core\BaseController {
            public function exposeTransform(mixed $data, ?\App\Core\Transformers\BaseTransformer $t = null): mixed {
                return $this->transformResponse($data, $t);
            }
        };

        $data = [
            'items' => [
                new \App\Modules\Feature\Feature(['id' => 'p1', 'name' => 'P1']),
            ],
            'total' => 1
        ];
        $result = $controller->exposeTransform($data, $transformer);
        $this->assertCount(1, $result['items']);
    }

    public function testUserSessionResolveRoleIdWithScalarRole(): void
    {
        $result = \App\Infrastructure\Auth\UserSession::resolveRoleId(['role' => 'admin']);
        $this->assertEquals('admin', $result);

        $result = \App\Infrastructure\Auth\UserSession::resolveRoleId(['role' => 123]);
        $this->assertEquals('123', $result);

        $result = \App\Infrastructure\Auth\UserSession::resolveRoleId([]);
        $this->assertEquals('', $result);
    }

    public function testUserSessionResolveRoleIdWithArrayRoleAndId(): void
    {
        $result = \App\Infrastructure\Auth\UserSession::resolveRoleId(['role' => ['id' => 'admin-role']]);
        $this->assertEquals('admin-role', $result);

        $result = \App\Infrastructure\Auth\UserSession::resolveRoleId(['role' => ['name' => 'No ID']]);
        $this->assertEquals('', $result);
    }

    public function testJsonErrorMiddlewareHandles401HttpException(): void
    {
        $middleware = new \App\Middleware\JsonErrorMiddleware();

        $uriMock = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uriMock->method('getPath')->willReturn('/test');

        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uriMock);
        $request->method('getMethod')->willReturn('GET');

        $exception = new \Slim\Exception\HttpUnauthorizedException($request, 'Unauthorized');

        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $result = $middleware->process($request, $handler);
        $this->assertEquals(401, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('UnauthorizedError', $body['error']);
    }

    public function testDatabaseProviderListenQueryExecuted(): void
    {
        $provider = \App\Infrastructure\Database\DatabaseProvider::getInstance();
        $called = false;
        $provider->listenQueryExecuted(function () use (&$called) {
            $called = true;
        });

        \Illuminate\Database\Capsule\Manager::table('roles')->first();
        $this->assertTrue($called);
    }

    public function testRateLimitAdminBypassWithJwtService(): void
    {
        $redis = $this->createMock(\Redis::class);

        $jwtMock = $this->createMock(\App\Infrastructure\Auth\JwtService::class);
        $jwtMock->method('validateToken')->willReturn(['roleId' => 'administrator']);

        $middleware = new \App\Middleware\RateLimitMiddleware($redis, $jwtMock);

        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn('/v1/auth/me');

        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaderLine')->with('Authorization')->willReturn('Bearer admin-token');
        $request->method('getAttribute')->willReturn('admin-123');

        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new \Slim\Psr7\Response());

        $response = $middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString((string) 60, $response->getHeaderLine('X-RateLimit-Limit'));
    }

    public function testRateLimitExceeded(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('incr')->willReturn(999);

        $middleware = new \App\Middleware\RateLimitMiddleware($redis);

        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn('/v1/auth/login');

        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getAttribute')->willReturn(null);

        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);
        $this->assertEquals(429, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Too Many Requests', $body['error']);
    }

    public function testRateLimitHealthRouteBypassed(): void
    {
        $redis = $this->createMock(\Redis::class);

        $middleware = new \App\Middleware\RateLimitMiddleware($redis);

        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn('/health');

        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn(new \Slim\Psr7\Response());

        $response = $middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRateLimitWithNonIntIncr(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('incr')->willReturn(false);

        $middleware = new \App\Middleware\RateLimitMiddleware($redis);

        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn('/v1/auth/login');

        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getAttribute')->willReturn(null);

        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new \Slim\Psr7\Response());

        $response = $middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAuthServiceChangePasswordUserNotFound(): void
    {
        $redis = $this->createMock(\Redis::class);
        $jwtMock = $this->createMock(\App\Infrastructure\Auth\JwtService::class);
        $authService = new \App\Modules\Auth\AuthService($jwtMock, $redis);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User not found');
        $authService->changePassword('nonexistent@test.com', 'token', 'newpass');
    }

    public function testHealthControllerGetUptimeUnknown(): void
    {
        $controller = new \App\Modules\Health\HealthController(
            $this->createMock(\Illuminate\Database\Capsule\Manager::class),
            $this->createMock(\Redis::class),
            $this->createMock(\Psr\Log\LoggerInterface::class)
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getUptime');
        $result = $method->invoke($controller, 'Windows');
        $this->assertEquals('Unknown', $result);

        $result = $method->invoke($controller, 'Linux');
        $this->assertNotEquals('Unknown', $result);
    }

    public function testAuthSchemasInstantiation(): void
    {
        $schemas = new \App\Modules\Auth\AuthSchemas();
        $this->assertInstanceOf(\App\Modules\Auth\AuthSchemas::class, $schemas);
    }

    public function testBaseRepositorySearch(): void
    {
        $repo = new class (\App\Modules\Role\Role::class) extends \App\Core\BaseRepository {
            public function __construct(string $modelClass) {
                $this->modelClass = $modelClass;
            }
        };

        \App\Modules\Role\Role::create(['id' => 'test-role-search', 'name' => 'Search Role']);
        $result = $repo->search([], [], 'name', 'asc', []);
        $this->assertNotEmpty($result);
    }

    public function testBaseRepositorySearchWithRelations(): void
    {
        $repo = new class (\App\Modules\Role\Role::class) extends \App\Core\BaseRepository {
            public function __construct(string $modelClass) {
                $this->modelClass = $modelClass;
            }
        };

        $result = $repo->search([], [], 'created_at', 'desc', ['features']);
        $this->assertIsArray($result);
    }

    public function testUserTransformer(): void
    {
        $transformer = new \App\Core\Transformers\UserTransformer();
        $user = new \App\Modules\User\User([
            'id' => 'transform-test-id',
            'name' => 'Transform User',
            'email' => 'transform@test.com',
            'id_role' => 'user',
            'active' => true
        ]);

        $result = $transformer->transform($user);
        $this->assertEquals('transform-test-id', $result['id']);
        $this->assertEquals('Transform User', $result['name']);
        $this->assertEquals('transform@test.com', $result['email']);
        $this->assertEquals('user', $result['id_role']);
    }

    public function testBaseControllerUpdateNotFound(): void
    {
        $serviceMock = $this->createMock(\App\Core\BaseService::class);
        $serviceMock->method('update')->willReturn(null);

        $controller = new class ($serviceMock) extends \App\Core\BaseController {
            public function __construct(\App\Core\BaseService $service) {
                $this->service = $service;
            }
        };

        $response = new \Slim\Psr7\Response();
        $result = $controller->update(
            (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('PUT', '/test'),
            $response,
            ['id' => '123']
        );
        $this->assertEquals(404, $result->getStatusCode());
    }

    public function testRateLimitInvalidJwtToken(): void
    {
        $redis = $this->createMock(\Redis::class);

        $jwtMock = $this->createMock(\App\Infrastructure\Auth\JwtService::class);
        $jwtMock->method('validateToken')->willReturn(null);

        $middleware = new \App\Middleware\RateLimitMiddleware($redis, $jwtMock);

        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn('/v1/auth/me');

        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaderLine')->with('Authorization')->willReturn('Bearer invalid-token');
        $request->method('getAttribute')->willReturn('user-123');

        $handler = $this->createMock(\Psr\Http\Server\RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new \Slim\Psr7\Response());

        $response = $middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString((string) 60, $response->getHeaderLine('X-RateLimit-Limit'));
    }

    public function testAuthControllerLogoutThroughApp(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        \App\Modules\User\User::create([
            'id' => $userId,
            'name' => 'Logout User',
            'email' => 'logout-user@test.com',
            'id_role' => 'administrator',
            'active' => true
        ]);

        $token = $this->getTokenForUser($userId);

        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('POST', '/v1/auth/logout');
        $request = $request->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Content-Type', 'application/json');

        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Logout successful', $body['message']);
        $this->assertTrue($body['valid']);
    }

    public function testAuthControllerJwksEndpoint(): void
    {
        $request = $this->createRequest('GET', '/v1/auth/.well-known/jwks.json');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('keys', $body);
    }

    public function testAuthControllerMeWithInvalidUserIdViaDirectCall(): void
    {
        $serviceMock = $this->createMock(\App\Modules\Auth\AuthService::class);
        $controller = new \App\Modules\Auth\AuthController($serviceMock);

        $this->expectException(\App\Core\Exceptions\BadRequestException::class);
        $this->expectExceptionMessage('Invalid user ID type');

        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/v1/auth/me');
        $response = new \Slim\Psr7\Response();

        // Remove userId attribute - controller expects it but we bypass AuthMiddleware
        $controller->me($request, $response);
    }

    public function testAuthServiceLogoutViaService(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('del')->willReturn(1);
        $redis->method('incr')->willReturn(2);

        $jwtService = new \App\Infrastructure\Auth\JwtService($redis);
        $authService = new \App\Modules\Auth\AuthService($jwtService, $redis);

        $authService->logout('test-user-id');
        $this->assertTrue(true);
    }

    public function testQueryApplierHelperRelationalWhereWithOrRules(): void
    {
        $repo = new class (\App\Modules\Role\Role::class) extends \App\Core\BaseRepository {
            public function __construct(string $modelClass) {
                $this->modelClass = $modelClass;
            }
        };

        \App\Modules\Role\Role::create(['id' => 'role-or-rel', 'name' => 'OR Rel']);
        $result = $repo->search(
            [
                ['field' => 'id', 'operator' => '=', 'value' => 'role-or-rel', 'relation' => null, 'relField' => null]
            ],
            [
                ['field' => 'name', 'operator' => 'LIKE', 'value' => 'OR', 'relation' => null, 'relField' => null]
            ]
        );
        $this->assertCount(1, $result);
    }

    public function testBaseControllerGetByIdNotFound(): void
    {
        $serviceMock = $this->createMock(\App\Core\BaseService::class);
        $serviceMock->method('retrieveById')->willReturn(null);

        $controller = new class ($serviceMock) extends \App\Core\BaseController {
            public function __construct(\App\Core\BaseService $service) {
                $this->service = $service;
            }
        };

        $response = new \Slim\Psr7\Response();
        $result = $controller->getById(
            (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/test/123'),
            $response,
            ['id' => '123']
        );
        $this->assertEquals(404, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('Record not found', $body['message']);
    }

    public function testBaseControllerDeleteNotFound(): void
    {
        $serviceMock = $this->createMock(\App\Core\BaseService::class);
        $serviceMock->method('delete')->willReturn(false);

        $controller = new class ($serviceMock) extends \App\Core\BaseController {
            public function __construct(\App\Core\BaseService $service) {
                $this->service = $service;
            }
        };

        $response = new \Slim\Psr7\Response();
        $result = $controller->delete(
            (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('DELETE', '/test/123'),
            $response,
            ['id' => '123']
        );
        $this->assertEquals(404, $result->getStatusCode());
    }

    public function testBaseControllerToggleStatusNotFound(): void
    {
        $serviceMock = $this->createMock(\App\Core\BaseService::class);
        $serviceMock->method('setStatus')->willReturn(null);

        $controller = new class ($serviceMock) extends \App\Core\BaseController {
            public function __construct(\App\Core\BaseService $service) {
                $this->service = $service;
            }
        };

        $response = new \Slim\Psr7\Response();
        $result = $controller->toggleStatus(
            (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('PATCH', '/test/123/status'),
            $response,
            ['id' => '123']
        );
        $this->assertEquals(404, $result->getStatusCode());
    }

    public function testAuthControllerLogoutWithInvalidUserIdViaDirectCall(): void
    {
        $serviceMock = $this->createMock(\App\Modules\Auth\AuthService::class);
        $controller = new \App\Modules\Auth\AuthController($serviceMock);

        $this->expectException(\App\Core\Exceptions\BadRequestException::class);
        $this->expectExceptionMessage('Invalid user ID type');

        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('POST', '/v1/auth/logout');
        $response = new \Slim\Psr7\Response();

        $controller->logout($request, $response);
    }

    public function testAuthServiceChangePasswordUserNotFoundAfterValidation(): void
    {
        $redis = $this->createMock(\Redis::class);
        $jwtMock = $this->createMock(\App\Infrastructure\Auth\JwtService::class);

        $authService = $this->getMockBuilder(\App\Modules\Auth\AuthService::class)
            ->onlyMethods(['validateResetToken'])
            ->setConstructorArgs([$jwtMock, $redis])
            ->getMock();

        $authService->expects($this->once())
            ->method('validateResetToken')
            ->willReturn(true);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User not found');
        $authService->changePassword('nonexistent-for-change@test.com', 'token', 'newpass');
    }

    public function testQueryApplierHelperRelationalFilterWithWhereHas(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        \App\Modules\User\User::create([
            'id' => $userId,
            'name' => 'Relational User',
            'email' => 'relational@test.com',
            'id_role' => 'administrator',
            'active' => true
        ]);

        $repo = new class (\App\Modules\User\User::class) extends \App\Core\BaseRepository {
            public function __construct(string $modelClass) {
                $this->modelClass = $modelClass;
            }
        };

        $result = $repo->search(
            [
                ['field' => 'role.name', 'operator' => '=', 'value' => 'Administrador', 'relation' => 'role', 'relField' => 'name']
            ],
            []
        );
        $this->assertCount(2, $result);
    }
}
