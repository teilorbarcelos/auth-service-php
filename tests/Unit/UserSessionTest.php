<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Auth\UserSession;
use PHPUnit\Framework\TestCase;

class UserSessionTest extends TestCase
{
    private UserSession $session;

    protected function setUp(): void
    {
        $this->session = new UserSession();
    }

    public function testSetAndGetUserId(): void
    {
        $this->session->setUserId('user-123');
        $this->assertEquals('user-123', $this->session->getUserId());

        $this->session->setUserId(null);
        $this->assertNull($this->session->getUserId());
    }

    public function testSetAndGetUser(): void
    {
        $user = ['uid' => 'user-123', 'name' => 'Test User'];
        $this->session->setUser($user);

        $this->assertEquals($user, $this->session->getUser());
        $this->assertEquals('user-123', $this->session->getUserId());

        $this->session->setUser(null);
        $this->assertNull($this->session->getUser());
    }

    public function testSetUserWithoutUid(): void
    {
        $user = ['name' => 'No UID'];
        $this->session->setUserId('keep-me');
        $this->session->setUser($user);

        $this->assertEquals($user, $this->session->getUser());
        $this->assertEquals('keep-me', $this->session->getUserId());
    }

    public function testHasPermission(): void
    {
        $user = [
            'uid' => 'user-123',
            'id_role' => 'manager',
            'permissions' => [
                ['feature' => 'user', 'view' => true, 'create' => false],
            ]
        ];
        $this->session->setUser($user);

        $this->assertTrue($this->session->hasPermission('user', 'view'));
        $this->assertFalse($this->session->hasPermission('user', 'create'));
        $this->assertFalse($this->session->hasPermission('product', 'view'));
    }

    public function testHasPermissionAdmin(): void
    {
        $user = [
            'uid' => 'admin-1',
            'id_role' => 'administrator',
            'permissions' => []
        ];
        $this->session->setUser($user);

        $this->assertTrue($this->session->hasPermission('anything', 'view'));
    }

    public function testHasPermissionNoUser(): void
    {
        $this->assertFalse($this->session->hasPermission('user', 'view'));
    }

    public function testIsAdmin(): void
    {
        $this->session->setUser(['uid' => '1', 'id_role' => 'administrator']);
        $this->assertTrue($this->session->isAdmin());

        $this->session->setUser(['uid' => '2', 'id_role' => 'user']);
        $this->assertFalse($this->session->isAdmin());

        $this->session->setUser(null);
        $this->assertFalse($this->session->isAdmin());
    }

    public function testGetInstance(): void
    {
        UserSession::resetInstance();
        $instance = UserSession::getInstance();
        $this->assertInstanceOf(UserSession::class, $instance);

        $same = UserSession::getInstance();
        $this->assertSame($instance, $same);
    }

    public function testSetInstance(): void
    {
        $session = new UserSession();
        $session->setUserId('custom-id');
        UserSession::setInstance($session);
        $this->assertSame('custom-id', UserSession::getInstance()->getUserId());
        UserSession::resetInstance();
    }
}
