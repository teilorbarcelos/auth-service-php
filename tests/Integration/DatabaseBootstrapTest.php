<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\DatabaseBootstrap;
use App\Modules\User\User;
use Tests\WebTestCase;

class DatabaseBootstrapTest extends WebTestCase
{
    public function testInitIsIdempotent(): void
    {
        $adminEmail = getenv('FIRST_USER') ?: 'admin@email.com';
        
        // Ensure user exists (first call in WebTestCase::setUp)
        $this->assertTrue(User::where('email', $adminEmail)->exists());
        
        // Second call should hit the early return branch
        DatabaseBootstrap::init();
        
        // Verify user still exists and no errors occurred
        $this->assertTrue(User::where('email', $adminEmail)->exists());
    }

    public function testInitCreatesUserIfNotFound(): void
    {
        // Delete all users to simulate first run
        \Illuminate\Database\Capsule\Manager::table('users')->delete();
        \Illuminate\Database\Capsule\Manager::table('auth')->delete();
        
        $adminEmail = getenv('FIRST_USER') ?: 'admin@email.com';
        $this->assertFalse(User::where('email', $adminEmail)->exists());
        
        // Call init
        DatabaseBootstrap::init();
        
        // Verify user created
        $this->assertTrue(User::where('email', $adminEmail)->exists());
        
        // Call init again for coverage of early return
        DatabaseBootstrap::init();
        $this->assertTrue(User::where('email', $adminEmail)->exists());
    }
}
