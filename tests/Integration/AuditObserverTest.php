<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Audit\Audit;
use App\Modules\Audit\AuditObserver;
use App\Modules\User\User;
use App\Infrastructure\Auth\UserSession;
use App\Infrastructure\Messaging\RabbitMQProvider;
use Tests\WebTestCase;

class AuditObserverTest extends WebTestCase
{
    protected function tearDown(): void
    {
        $_ENV['AUDIT_ASYNC'] = 'false';
        parent::tearDown();
    }

    public function testAuditObserverSkipsAuditTable(): void
    {
        $session = new UserSession();
        $observer = new AuditObserver($session);
        $auditModel = new Audit();

        $observer->created($auditModel);

        $count = Audit::where('table_name', 'audit.tb_audit')->count();
        $this->assertEquals(0, $count);
    }

    public function testAuditUpdate(): void
    {
        $user = User::create([
            'id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Audit User',
            'email' => 'audit@test.com',
            'id_role' => 'administrator'
        ]);

        $user->name = 'Updated Audit User';
        $user->save();

        $audit = Audit::where('table_name', 'users')
            ->where('action_type', 'UPDATE')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('Updated Audit User', (string)$audit->diff_value);
    }

    public function testAuditDelete(): void
    {
        $user = User::create([
            'id' => '00000000-0000-0000-0000-000000000003',
            'name' => 'Real Delete User',
            'email' => 'real-delete@test.com',
            'id_role' => 'administrator'
        ]);

        $session = new UserSession();
        $observer = new AuditObserver($session);
        $observer->deleted($user);

        $audit = Audit::where('table_name', 'users')
            ->where('action_type', 'DELETE')
            ->first();

        $this->assertNotNull($audit);
    }

    public function testAuditObserverUsesMessagingProviderWhenEnabled(): void
    {
        $_ENV['AUDIT_ASYNC'] = 'true';

        $provider = $this->createMock(RabbitMQProvider::class);
        $provider->expects($this->once())->method('publish');

        $session = new UserSession();
        $observer = new AuditObserver($session, $provider);

        // Directly invoke the observer method (bypasses Eloquent event system)
        $user = User::create([
            'id' => '00000000-0000-0000-0000-000000000005',
            'name' => 'Async Audit',
            'email' => 'async-audit@test.com',
            'id_role' => 'administrator'
        ]);

        $observer->created($user);
    }

    public function testAuditObserverConstructorCreatesWithContainer(): void
    {
        $observer = $this->getContainer()->get(AuditObserver::class);
        $this->assertInstanceOf(AuditObserver::class, $observer);
    }
}
