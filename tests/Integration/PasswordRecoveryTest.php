<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\User\User;
use App\Modules\User\UserAuth;
use Tests\WebTestCase;

class PasswordRecoveryTest extends WebTestCase
{
    public function testFullPasswordRecoveryFlow(): void
    {
        $email = 'recovery@test.com';
        $userId = (string) \Illuminate\Support\Str::uuid();
        
        // Setup user
        User::create([
            'id' => $userId,
            'name' => 'Recovery User',
            'email' => $email,
            'id_role' => 'user'
        ]);

        UserAuth::create([
            'id' => $userId,
            'password' => password_hash('old-password', PASSWORD_DEFAULT)
        ]);

        // 1. Request Password Reset
        $response = $this->request('POST', '/v1/auth/password/request', ['email' => $email]);
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify token in DB
        $auth = UserAuth::find($userId);
        $this->assertNotNull($auth->request_password_token);
        $this->assertNotNull($auth->request_password_expiration);
        $token = $auth->request_password_token;

        // 2. Validate Token
        $response = $this->request('POST', '/v1/auth/password/validate', [
            'email' => $email,
            'token' => $token
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['valid']);

        // 3. Change Password
        $response = $this->request('POST', '/v1/auth/password/change', [
            'email' => $email,
            'token' => $token,
            'password' => 'new-password'
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        // 4. Verify login with new password
        $response = $this->request('POST', '/v1/auth/login', [
            'email' => $email,
            'password' => 'new-password'
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify old password doesn't work
        $response = $this->request('POST', '/v1/auth/login', [
            'email' => $email,
            'password' => 'old-password'
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testInvalidResetToken(): void
    {
        $email = 'invalid-token@test.com';
        $userId = (string) \Illuminate\Support\Str::uuid();
        
        User::create([
            'id' => $userId,
            'name' => 'Invalid User',
            'email' => $email,
            'id_role' => 'user'
        ]);

        UserAuth::create([
            'id' => $userId,
            'password' => 'pass',
            'request_password_token' => '123456',
            'request_password_expiration' => (new \DateTime('+15 minutes'))->format('Y-m-d H:i:s')
        ]);

        $response = $this->request('POST', '/v1/auth/password/validate', [
            'email' => $email,
            'token' => 'wrong-token'
        ]);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testExpiredResetToken(): void
    {
        $email = 'expired@test.com';
        $userId = (string) \Illuminate\Support\Str::uuid();
        
        User::create([
            'id' => $userId,
            'name' => 'Expired User',
            'email' => $email,
            'id_role' => 'user'
        ]);

        UserAuth::create([
            'id' => $userId,
            'password' => 'pass',
            'request_password_token' => '123456',
            'request_password_expiration' => '2000-01-01 00:00:00'
        ]);

        $response = $this->request('POST', '/v1/auth/password/validate', [
            'email' => $email,
            'token' => '123456'
        ]);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testRequestResetUserWithoutAuthRecord(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'No Auth User',
            'email' => 'noauth_reset@example.com',
            'id_role' => 'user'
        ]);
        
        $response = $this->request('POST', '/v1/auth/password/request', ['email' => 'noauth_reset@example.com']);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRequestResetNonExistentUser(): void
    {
        $response = $this->request('POST', '/v1/auth/password/request', ['email' => 'nonexistent@test.com']);
        // Should still return 200 to avoid email enumeration
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testValidateTokenUserNotFound(): void
    {
        $response = $this->request('POST', '/v1/auth/password/validate', [
            'email' => 'nonexistent@test.com',
            'token' => '123456'
        ]);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testResetPasswordUserNotFound(): void
    {
        $response = $this->request('POST', '/v1/auth/password/change', [
            'email' => 'nonexistent@test.com',
            'token' => '123456',
            'password' => 'new-pass'
        ]);
        $this->assertEquals(404, $response->getStatusCode());
    }
}
