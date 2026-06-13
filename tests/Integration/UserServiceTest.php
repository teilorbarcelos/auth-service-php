<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\User\UserService;
use App\Modules\User\UserRepository;
use App\Modules\User\User;
use Tests\WebTestCase;

class UserServiceTest extends WebTestCase
{
    private UserService $userService;
    private $pdfProviderMock;

    protected function setUp(): void
    {
        parent::setUp();
        $jwtService = new \App\Infrastructure\Auth\JwtService($this->createMock(\Redis::class));
        $this->pdfProviderMock = $this->createMock(\App\Infrastructure\Pdf\PdfProviderInterface::class);
        $this->userService = new UserService(new UserRepository(), $jwtService, $this->pdfProviderMock);
    }

    public function testListUsers(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'User 1',
            'email' => 'u1@test.com',
            'id_role' => 'user'
        ]);

        $result = $this->userService->listItems([]);
        $this->assertEquals(2, $result['total']); // admin + new user
        // The results are usually ordered by created_at desc or id
        // Let's check if 'User 1' is in the results
        $names = array_column($result['items'], 'name');
        $this->assertContains('User 1', $names);
    }
    public function testFindByEmail(): void
    {
        $repository = new UserRepository();
        $user = $repository->findByEmail('admin@email.com');
        $this->assertNotNull($user);
        $this->assertEquals('admin@email.com', $user->email);

        $this->assertNull($repository->findByEmail('nonexistent@test.com'));
    }

    public function testSearchByRoleName(): void
    {
        // Administrador role is seeded by DatabaseBootstrap::init()
        
        // 1. Filter by role.name (andRule with dot)
        $result = $this->userService->listItems(['role.name' => 'Administrador']);
        $this->assertGreaterThanOrEqual(1, $result['total']);
        $this->assertNotNull($result['items'][0]->role);
        $this->assertEquals('Administrador', $result['items'][0]->role->name);

        // 2. Global search with role.name (orRule with dot)
        $result = $this->userService->listItems([
            'searchWord' => 'Admin',
            'searchFields' => 'name,role.name'
        ]);
        $this->assertGreaterThanOrEqual(1, $result['total']);
        
        // 3. Test normalization (Role.name -> role.name)
        $result = $this->userService->listItems([
            'searchWord' => 'Administrador',
            'searchFields' => 'Role.name'
        ]);
        $this->assertGreaterThanOrEqual(1, $result['total']);
    }

    public function testCreateUserValidationFail(): void
    {
        $this->expectException(\App\Core\Exceptions\ValidationException::class);
        
        $this->userService->create([
            'name' => 'Ab', // too short
            'email' => 'invalid-email',
            'id_role' => ''
        ]);
    }

    public function testUpdateUser(): void
    {
        $user = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Update Me',
            'email' => 'update@test.com',
            'id_role' => 'user'
        ]);

        $result = $this->userService->update($user->id, ['name' => 'Updated Name', 'active' => false]);
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('Updated Name', $result->name);
        $this->assertFalse($result->active);
    }

    public function testSetStatus(): void
    {
        $user = User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Status Me',
            'email' => 'status@test.com',
            'id_role' => 'user',
            'active' => true
        ]);

        $result = $this->userService->setStatus($user->id, false);
        $this->assertInstanceOf(User::class, $result);
        $this->assertFalse($result->active);
    }

    public function testExportPdf(): void
    {
        $mockStream = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        
        $this->pdfProviderMock->expects($this->once())
            ->method('generatePdf')
            ->with($this->callback(function($payload) {
                $this->assertEquals('user-list', $payload['template']);
                $this->assertArrayHasKey('title', $payload['data']);
                $this->assertArrayHasKey('generatedAt', $payload['data']);
                $this->assertArrayHasKey('users', $payload['data']);
                $users = $payload['data']['users'];
                $this->assertIsArray($users);
                
                $adminEmails = array_column($users, 'email');
                $this->assertContains('admin@email.com', $adminEmails);
                return true;
            }))
            ->willReturn($mockStream);

        $result = $this->userService->exportPdf([]);
        $this->assertSame($mockStream, $result);
    }

    public function testExportPdfWithFilters(): void
    {
        $mockStream = $this->createMock(\Psr\Http\Message\StreamInterface::class);

        $this->pdfProviderMock->expects($this->once())
            ->method('generatePdf')
            ->with($this->callback(function($payload) {
                $this->assertEquals('user-list', $payload['template']);
                $users = $payload['data']['users'];
                $this->assertIsArray($users);
                
                $this->assertCount(1, $users);
                $this->assertEquals('admin@email.com', $users[0]['email']);
                return true;
            }))
            ->willReturn($mockStream);

        $result = $this->userService->exportPdf([
            'email' => 'admin@email.com',
            'orderBy' => 'name',
            'orderDirection' => 'asc'
        ]);
        $this->assertSame($mockStream, $result);
    }
}
