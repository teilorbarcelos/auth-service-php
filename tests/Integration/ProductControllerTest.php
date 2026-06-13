<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\User\User;
use App\Modules\Product\Product;
use App\Infrastructure\Auth\JwtService;
use Tests\WebTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class ProductControllerTest extends WebTestCase
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
            ['feature' => 'product', 'view' => true, 'create' => true, 'delete' => true, 'activate' => true]
        ]);
    }

    public function testListProducts(): void
    {
        Product::create([
            'sku' => 'P1',
            'name' => 'Prod 1',
            'category' => 'Test',
            'price' => 10,
            'stock' => 5,
            'active' => true
        ]);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/product');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        /** @var array{total: int} $body */
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(1, $body['total']);
    }

    public function testCreateProduct(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/v1/product');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request->getBody()->write((string)json_encode([
            'sku' => 'NEW-P',
            'name' => 'New Prod',
            'category' => 'Electronics',
            'description' => 'A new product description',
            'price' => 20,
            'stock' => 10
        ]));

        $response = $this->app->handle($request);
        $this->assertEquals(201, $response->getStatusCode());
    }
    public function testListAllProducts(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/product/all');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetProductById(): void
    {
        $p = Product::create(['sku' => 'P2', 'name' => 'Prod 2', 'category' => 'Test', 'price' => 10, 'stock' => 5]);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/product/' . $p->id);
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUpdateProduct(): void
    {
        $p = Product::create(['sku' => 'P3', 'name' => 'Prod 3', 'category' => 'Test', 'price' => 10, 'stock' => 5]);
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/v1/product/' . $p->id);
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request->getBody()->write((string)json_encode(['name' => 'Updated Name']));
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteProduct(): void
    {
        $p = Product::create(['sku' => 'P4', 'name' => 'Prod 4', 'category' => 'Test', 'price' => 10, 'stock' => 5]);
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', '/v1/product/' . $p->id);
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $response = $this->app->handle($request);
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testToggleProductStatus(): void
    {
        $p = Product::create(['sku' => 'P5', 'name' => 'Prod 5', 'category' => 'Test', 'price' => 10, 'stock' => 5]);
        $request = (new ServerRequestFactory())->createServerRequest('PATCH', '/v1/product/' . $p->id . '/status');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request->getBody()->write((string)json_encode(['active' => false]));
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
