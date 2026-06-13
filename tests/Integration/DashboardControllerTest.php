<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\User\User;
use App\Modules\Product\Product;
use Tests\WebTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class DashboardControllerTest extends WebTestCase
{
    private string $adminToken;
    private string $noPermToken;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminEmail = getenv('FIRST_USER') ?: 'admin@email.com';
        $admin = User::where('email', $adminEmail)->first();
        $this->assertNotNull($admin);
        $this->admin = $admin;

        // Admin token with dashboard view permission
        $this->adminToken = $this->getTokenForUser($admin->id, [
            ['feature' => 'dashboard', 'view' => true]
        ]);

        // Create a non-admin user to test forbidden/permission checks
        $testUser = User::firstOrCreate(['email' => 'testuser@test.com'], [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test User',
            'email' => 'testuser@test.com',
            'id_role' => 'user',
            'active' => true
        ]);

        // Token without dashboard view permission
        $this->noPermToken = $this->getTokenForUser($testUser->id, [
            ['feature' => 'dashboard', 'view' => false]
        ]);
    }

    public function testGetStatsUnauthenticated(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/dashboard/stats');
        
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetStatsForbidden(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/dashboard/stats');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->noPermToken);
        
        $response = $this->app->handle($request);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGetStatsAuthorized(): void
    {
        // Setup some test data
        // Clear existing products and users (except admin)
        Product::query()->delete();
        User::where('id', '!=', $this->admin->id)->delete();

        // Create Users with specific creation dates using non-timestamped save
        $user1 = new User([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'User One',
            'email' => 'user1@test.com',
            'id_role' => $this->admin->id_role,
            'active' => true,
        ]);
        $user1->timestamps = false;
        $user1->created_at = '2026-05-20 10:00:00';
        $user1->save();

        $user2 = new User([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'User Two',
            'email' => 'user2@test.com',
            'id_role' => $this->admin->id_role,
            'active' => true,
        ]);
        $user2->timestamps = false;
        $user2->created_at = '2026-05-21 14:00:00';
        $user2->save();

        // Create Products with specific creation dates and users
        $prodA = new Product([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'sku' => 'PROD-A',
            'name' => 'Product A',
            'price' => 10,
            'stock' => 5,
            'id_user' => $user1->id,
        ]);
        $prodA->timestamps = false;
        $prodA->created_at = '2026-05-20 12:00:00';
        $prodA->save();

        $prodB = new Product([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'sku' => 'PROD-B',
            'name' => 'Product B',
            'price' => 15,
            'stock' => 10,
            'id_user' => $user1->id,
        ]);
        $prodB->timestamps = false;
        $prodB->created_at = '2026-05-20 13:00:00';
        $prodB->save();

        $prodC = new Product([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'sku' => 'PROD-C',
            'name' => 'Product C',
            'price' => 20,
            'stock' => 15,
            'id_user' => $user2->id,
        ]);
        $prodC->timestamps = false;
        $prodC->created_at = '2026-05-21 09:00:00';
        $prodC->save();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/dashboard/stats?createdAt_start=2026-05-20&createdAt_end=2026-05-21');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->adminToken);

        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);

        $this->assertArrayHasKey('userCreationStats', $body);
        $this->assertArrayHasKey('productCreationStats', $body);
        $this->assertArrayHasKey('productsPerUser', $body);

        // Check user stats
        $userStats = $body['userCreationStats'];
        $this->assertCount(2, $userStats);
        $this->assertEquals('2026-05-20', $userStats[0]['date']);
        $this->assertEquals(1, $userStats[0]['count']);
        $this->assertEquals('2026-05-21', $userStats[1]['date']);
        $this->assertEquals(1, $userStats[1]['count']);

        // Check product stats
        $productStats = $body['productCreationStats'];
        $this->assertCount(2, $productStats);
        $this->assertEquals('2026-05-20', $productStats[0]['date']);
        $this->assertEquals(2, $productStats[0]['count']);
        $this->assertEquals('2026-05-21', $productStats[1]['date']);
        $this->assertEquals(1, $productStats[1]['count']);

        // Check products per user
        $productsPerUser = $body['productsPerUser'];
        $this->assertCount(2, $productsPerUser);
        
        $this->assertEquals($user1->id, $productsPerUser[0]['userId']);
        $this->assertEquals('User One', $productsPerUser[0]['userName']);
        $this->assertEquals(2, $productsPerUser[0]['count']);

        $this->assertEquals($user2->id, $productsPerUser[1]['userId']);
        $this->assertEquals('User Two', $productsPerUser[1]['userName']);
        $this->assertEquals(1, $productsPerUser[1]['count']);
    }

    public function testGetStatsEmptyParams(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/dashboard/stats');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->adminToken);

        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('userCreationStats', $body);
        $this->assertArrayHasKey('productCreationStats', $body);
        $this->assertArrayHasKey('productsPerUser', $body);
    }

    public function testGetStatsInvalidAndArrayParams(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/dashboard/stats?createdAt_start[]=invalid-date&createdAt_end[]=invalid-date');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->adminToken);

        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('userCreationStats', $body);
        $this->assertArrayHasKey('productCreationStats', $body);
        $this->assertArrayHasKey('productsPerUser', $body);
    }

    public function testGetStatsTimezoneAware(): void
    {
        $originalTz = date_default_timezone_get();
        date_default_timezone_set('America/Sao_Paulo');

        try {
            Product::query()->delete();
            User::where('id', '!=', $this->admin->id)->delete();

            // Created at 2026-05-26 01:00:00 UTC (which is 2026-05-25 22:00:00 America/Sao_Paulo)
            $user = new User([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'name' => 'TZ User',
                'email' => 'tzuser@test.com',
                'id_role' => $this->admin->id_role,
                'active' => true,
            ]);
            $user->timestamps = false;
            $user->created_at = '2026-05-26 01:00:00';
            $user->save();

            // Query for 2026-05-25 local time
            $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/dashboard/stats?createdAt_start=2026-05-25&createdAt_end=2026-05-25');
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->adminToken);

            $response = $this->app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());

            $body = json_decode((string)$response->getBody(), true);
            $this->assertCount(1, $body['userCreationStats']);
        } finally {
            date_default_timezone_set($originalTz);
        }
    }
}
