<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\User\User;
use App\Modules\User\UserAuth;
use App\Infrastructure\Auth\JwtService;
use Tests\WebTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use App\Modules\Auth\AuthController;

class AuthControllerTest extends WebTestCase
{
    public function testConstructor(): void
    {
        $service = $this->createMock(\App\Modules\Auth\AuthService::class);
        $controller = new AuthController($service);
        $this->assertInstanceOf(AuthController::class, $controller);
    }

    public function testLoginRoute(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'Login User',
            'email' => 'login@test.com',
            'id_role' => 'administrator',
            'active' => true
        ]);

        UserAuth::create([
            'id' => $userId,
            'password' => password_hash('pass123', PASSWORD_DEFAULT),
            'first_access' => false
        ]);

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/v1/auth/login');
        $request->getBody()->write((string)json_encode([
            'email' => 'login@test.com',
            'password' => 'pass123'
        ]));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        /** @var array{token: string} $body */
        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('token', $body);
    }

    public function testMeRoute(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'Me User',
            'email' => 'me@test.com',
            'id_role' => 'administrator',
            'active' => true
        ]);

        $token = $this->getTokenForUser($userId);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/auth/me');
        $request = $request->withHeader('Authorization', 'Bearer ' . $token);

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        /** @var array{user: array{email: string}} $body */
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('me@test.com', $body['user']['email']);
    }

    public function testRefreshRoute(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'Refresh User',
            'email' => 'refresh@test.com',
            'id_role' => 'administrator',
            'active' => true
        ]);

        /** @var \App\Infrastructure\Auth\JwtService $jwtService */
        $jwtService = $this->getContainer()->get(\App\Infrastructure\Auth\JwtService::class);
        $tokens = $jwtService->createTokenPair($userId, ['email' => 'refresh@test.com']);
        $jwtService->registerTokens($userId, [$tokens['refreshToken']]);

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/v1/auth/refresh');
        $request->getBody()->write((string)json_encode([
            'refreshToken' => $tokens['refreshToken']
        ]));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        /** @var array{token: string, refreshToken: string} $body */
        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('token', $body);
        $this->assertArrayHasKey('refreshToken', $body);
        $this->assertEquals('refresh@test.com', $body['user']['email']);
    }

    public function testLoginInvalidInputTypes(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/v1/auth/login');
        $request->getBody()->write((string)json_encode([
            'email' => 123, // Invalid type
            'password' => 'pass123'
        ]));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Invalid input types for login', $body['error']['message']);
    }

    public function testRefreshInvalidInputType(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/v1/auth/refresh');
        $request->getBody()->write((string)json_encode([
            'refreshToken' => 123 // Invalid type
        ]));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $this->app->handle($request);
        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Invalid input type for refresh token', $body['error']['message']);
    }

    public function testLoginInvalidCredentials(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/v1/auth/login');
        $request->getBody()->write((string)json_encode([
            'email' => 'nonexistent@test.com',
            'password' => 'pass123'
        ]));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testMeUserNotFound(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        // Create token for a user that doesn't exist in DB
        $token = $this->getTokenForUser($userId);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/auth/me');
        $request = $request->withHeader('Authorization', 'Bearer ' . $token);

        $response = $this->app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRefreshInvalidToken(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/v1/auth/refresh');
        $request->getBody()->write((string)json_encode([
            'refreshToken' => 'invalid.token.here'
        ]));
        $request = $request->withHeader('Content-Type', 'application/json');

        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }
}
