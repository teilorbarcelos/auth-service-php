<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Auth\AuthService;
use App\Modules\User\User;
use App\Modules\User\UserAuth;
use App\Infrastructure\Auth\JwtService;
use Tests\WebTestCase;

class AuthServiceTest extends WebTestCase
{
    private AuthService $authService;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\Redis */
    private $redisMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->redisMock = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();
            
        $this->redisMock->method('sadd')->willReturn(1);
        $this->redisMock->method('expire')->willReturn(true);
        $this->redisMock->method('get')->willReturn('1');
        $this->redisMock->method('setnx')->willReturn(true);
        $this->redisMock->method('incr')->willReturn(2);
        $this->redisMock->method('srem')->willReturn(1);
        
        $jwtService = new JwtService($this->redisMock);
        $emailProviderMock = $this->createMock(\App\Infrastructure\Email\EmailProvider::class);
        $this->authService = new AuthService($jwtService, $emailProviderMock);
    }

    public function testLoginSuccess(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        // Setup user
        $user = User::create([
            'id' => $userId,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'id_role' => 'administrator',
            'active' => true
        ]);

        UserAuth::create([
            'id' => $userId,
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'first_access' => false
        ]);

        /** @var array{user: array{email: string}} $result */
        $result = $this->authService->login('test@example.com', 'password123');

        $this->assertArrayHasKey('token', $result);
        $this->assertEquals('test@example.com', $result['user']['email']);
    }

    public function testLoginInvalidUser(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid credentials');
        $this->expectExceptionCode(401);

        $this->authService->login('nonexistent@example.com', 'password');
    }

    public function testLoginInvalidPassword(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
            'id_role' => 'user',
            'active' => true
        ]);

        UserAuth::create([
            'id' => $userId,
            'password' => password_hash('password123', PASSWORD_DEFAULT)
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid credentials');
        $this->expectExceptionCode(401);

        $this->authService->login('test2@example.com', 'wrongpassword');
    }

    public function testLoginDisabledAccount(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'Disabled User',
            'email' => 'disabled@example.com',
            'id_role' => 'user',
            'active' => false
        ]);

        UserAuth::create([
            'id' => $userId,
            'password' => password_hash('password123', PASSWORD_DEFAULT)
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Account is disabled');
        $this->expectExceptionCode(403);

        $this->authService->login('disabled@example.com', 'password123');
    }

    public function testLoginDisabledRole(): void
    {
        $roleId = 'disabled-role';
        \App\Modules\Role\Role::create([
            'id' => $roleId,
            'name' => 'Disabled Role',
            'active' => false
        ]);

        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'Disabled Role User',
            'email' => 'disabledrole@example.com',
            'id_role' => $roleId,
            'active' => true
        ]);

        UserAuth::create([
            'id' => $userId,
            'password' => password_hash('password123', PASSWORD_DEFAULT)
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Role is disabled');
        $this->expectExceptionCode(403);

        $this->authService->login('disabledrole@example.com', 'password123');
    }

    public function testGetMeSuccess(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'Me User',
            'email' => 'me@example.com',
            'id_role' => 'administrator',
            'active' => true
        ]);

        /** @var array{user: array{email: string}} $result */
        $result = $this->authService->getMe($userId);
        $this->assertEquals('me@example.com', $result['user']['email']);
    }

    public function testLoginWithPermissions(): void
    {
        $roleId = 'manager';
        $featureId = 'feature-x';
        
        \App\Modules\Role\Role::create(['id' => $roleId, 'name' => 'Manager']);
        \App\Modules\Feature\Feature::create(['id' => $featureId, 'name' => 'Feature X']);
        
        // Setup pivot data
        \Illuminate\Database\Capsule\Manager::table('role_features')->insert([
            'id_role' => $roleId,
            'id_feature' => $featureId,
            'permissions' => json_encode(['view' => true, 'create' => false])
        ]);

        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'Manager User',
            'email' => 'manager@test.com',
            'id_role' => $roleId,
            'active' => true
        ]);

        UserAuth::create([
            'id' => $userId,
            'password' => password_hash('pass', PASSWORD_DEFAULT)
        ]);

        /** @var array{user: array{email: string, role: array{permissions: array<int, array{feature: string, view: bool, create: bool}>}}} $result */
        $result = $this->authService->login('manager@test.com', 'pass');

        $this->assertCount(1, $result['user']['role']['permissions']);
        $this->assertEquals($featureId, $result['user']['role']['permissions'][0]['feature']);
        $this->assertTrue($result['user']['role']['permissions'][0]['view']);
        $this->assertFalse($result['user']['role']['permissions'][0]['create']);
    }

    public function testGetMeUserNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User not found');
        $this->expectExceptionCode(404);

        $this->authService->getMe((string)\Illuminate\Support\Str::uuid());
    }

    public function testRefreshTokenSuccess(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'Refresh User',
            'email' => 'refresh@example.com',
            'id_role' => 'administrator',
            'active' => true
        ]);

        UserAuth::create([
            'id' => $userId,
            'password' => password_hash('password123', PASSWORD_DEFAULT)
        ]);

        $loginResult = $this->authService->login('refresh@example.com', 'password123');
        $refreshToken = $loginResult['refreshToken'];

        $this->redisMock->method('sismember')->willReturn(true);
        $refreshResult = $this->authService->refreshToken($refreshToken);

        $this->assertArrayHasKey('token', $refreshResult);
        $this->assertEquals('refresh@example.com', $refreshResult['user']['email']);
        $this->assertEquals('User found', $refreshResult['message']);
    }

    public function testRefreshTokenInvalidToken(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid or expired refresh token');
        $this->expectExceptionCode(401);

        $this->authService->refreshToken('invalid-token');
    }

    public function testRefreshTokenRevoked(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'Revoked User',
            'email' => 'revoked@example.com',
            'id_role' => 'administrator',
            'active' => true
        ]);

        $jwtService = new JwtService($this->redisMock);
        $refreshToken = $jwtService->createToken($userId);
        
        $this->redisMock->expects($this->once())
            ->method('sismember')
            ->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid or expired refresh token');
        
        $this->authService->refreshToken($refreshToken);
    }

    public function testLoginUserWithoutAuthRecord(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'No Auth User',
            'email' => 'noauth@example.com',
            'id_role' => 'user',
            'active' => true
        ]);
        // Note: No UserAuth created

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid credentials');
        $this->authService->login('noauth@example.com', 'pass');
    }

    public function testGetMeUserWithoutRole(): void
    {
        $roleId = 'nonexistent-role';
        \App\Modules\Role\Role::create(['id' => $roleId, 'name' => 'Non Existent']);

        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'No Role User',
            'email' => 'norole@example.com',
            'id_role' => 'nonexistent-role',
            'active' => true
        ]);

        $result = $this->authService->getMe($userId);
        $this->assertEmpty($result['user']['role']['permissions']);
    }
}
