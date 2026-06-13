<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\User\User;
use App\Modules\Feature\Feature;
use App\Infrastructure\Auth\JwtService;
use Tests\WebTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class FeatureControllerTest extends WebTestCase
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
            ['feature' => 'feature', 'view' => true]
        ]);
    }

    public function testListFeatures(): void
    {
        Feature::create(['id' => 'f1', 'name' => 'Feat 1']);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/feature');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
    public function testListAllFeatures(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/feature/all');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetFeatureById(): void
    {
        Feature::create(['id' => 'f2', 'name' => 'Feat 2']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/feature/f2');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
