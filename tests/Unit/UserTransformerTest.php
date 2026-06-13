<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Transformers\UserTransformer;
use App\Modules\User\User;
use Tests\WebTestCase;

class UserTransformerTest extends WebTestCase
{
    public function testTransform(): void
    {
        $transformer = new UserTransformer();
        $user = new User([
            'id' => 'user-123',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'id_role' => 'user',
            'active' => true
        ]);

        $result = $transformer->transform($user);

        $this->assertEquals('user-123', $result['id']);
        $this->assertEquals('Test User', $result['name']);
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals('user', $result['id_role']);
        $this->assertTrue($result['active']);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('updated_at', $result);
    }
}
