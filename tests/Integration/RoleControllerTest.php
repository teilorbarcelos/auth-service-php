<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\User\User;
use App\Modules\Role\Role;
use App\Infrastructure\Auth\JwtService;
use Tests\WebTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class RoleControllerTest extends WebTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $adminEmail = getenv('FIRST_USER') ?: 'admin@email.com';
        /** @var User $admin */
        $admin = User::where('email', $adminEmail)->first();
        $this->assertNotNull($admin);
        
        $this->token = $this->getTokenForUser($admin->id, [
            ['feature' => 'role', 'view' => true, 'create' => true, 'delete' => true, 'activate' => true]
        ]);
    }

    public function testListRoles(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/role');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetRoleById(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/role/administrator');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
    public function testListAllRoles(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/role/all');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCreateRole(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/v1/role');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request->getBody()->write((string)json_encode([
            'id' => 'new-role',
            'name' => 'New Role'
        ]));
        $response = $this->app->handle($request);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testUpdateRole(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/v1/role/administrator');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request->getBody()->write((string)json_encode(['name' => 'Admin Updated']));
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteRole(): void
    {
        Role::create(['id' => 'to-delete', 'name' => 'To Delete']);
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', '/v1/role/to-delete');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $response = $this->app->handle($request);
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testToggleRoleStatus(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('PATCH', '/v1/role/administrator/status');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request->getBody()->write((string)json_encode(['active' => false]));
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testListFeatures(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/role/features');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRoleNotFound(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/role/non-existent');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $response = $this->app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
    }
}
