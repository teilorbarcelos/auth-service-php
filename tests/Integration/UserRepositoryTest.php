<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\User\User;
use App\Modules\User\UserAuth;
use App\Modules\User\UserRepository;
use Tests\WebTestCase;

class UserRepositoryTest extends WebTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository();
    }

    public function testFindByEmail(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'Find Me',
            'email' => 'findme@example.com',
            'id_role' => 'administrator'
        ]);

        $found = $this->repository->findByEmail('findme@example.com');
        $this->assertInstanceOf(User::class, $found);
        $this->assertEquals('findme@example.com', $found->email);

        $notFound = $this->repository->findByEmail('nonexistent@example.com');
        $this->assertNull($notFound);
    }

    public function testDeleteAnonymizesUser(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        User::create([
            'id' => $userId,
            'name' => 'To Delete',
            'email' => 'todelete@example.com',
            'id_role' => 'administrator'
        ]);

        $result = $this->repository->delete($userId);
        $this->assertTrue($result);

        $deleted = User::withTrashed()->find($userId);
        $this->assertStringContainsString('Deleted User', $deleted->name);
        $this->assertStringContainsString('deleted-', $deleted->email);
        $this->assertFalse((bool) $deleted->active);
        $this->assertTrue((bool) $deleted->is_deleted);
        $this->assertNotNull($deleted->deleted_at);
    }

    public function testDeleteNotFound(): void
    {
        $result = $this->repository->delete('00000000-0000-0000-0000-000000000000');
        $this->assertFalse($result);
    }
}
