<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\User\User;
use App\Infrastructure\Auth\JwtService;
use App\Infrastructure\Pdf\PdfProviderInterface;
use Tests\WebTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

class UserRoutesTest extends WebTestCase
{
    private string $token;
    private string $adminId;
    private $pdfProviderMock;

    protected function setUp(): void
    {
        self::$staticApp = null;
        parent::setUp();

        $this->pdfProviderMock = $this->createMock(PdfProviderInterface::class);
        $container = $this->app->getContainer();
        $container->set(PdfProviderInterface::class, $this->pdfProviderMock);

        // Use the admin created by DatabaseBootstrap
        $adminEmail = getenv('FIRST_USER') ?: 'admin@email.com';
        /** @var User $admin */
        $admin = User::where('email', $adminEmail)->first();
        $this->assertNotNull($admin);
        $this->adminId = $admin->id;

        $this->token = $this->getTokenForUser($admin->id, [
            ['feature' => 'user', 'view' => true, 'create' => true, 'delete' => true, 'activate' => true]
        ]);
    }

    public function testRootRoute(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUserListUnauthorized(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/user');
        $response = $this->app->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUserListAuthorized(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/user');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        /** @var array{items: array<mixed>} $body */
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('items', $body);
    }

    public function testCreateUser(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/v1/user');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request->getBody()->write((string)json_encode([
            'name' => 'New User',
            'email' => 'new@test.com',
            'id_role' => 'administrator'
        ]));

        $response = $this->app->handle($request);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testGetUserById(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/user/' . $this->adminId);
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);

        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
    public function testUpdateUser(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/v1/user/' . $this->adminId);
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request->getBody()->write((string)json_encode([
            'name' => 'Updated Name'
        ]));

        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteUser(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('DELETE', '/v1/user/' . $this->adminId);
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);

        $response = $this->app->handle($request);
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testToggleUserStatus(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('PATCH', '/v1/user/' . $this->adminId . '/status');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request->getBody()->write((string)json_encode([
            'active' => false
        ]));

        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testListAllUsers(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/user/all');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);

        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testExportPdfUnauthorized(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/user/export/pdf');
        $response = $this->app->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testExportPdfForbidden(): void
    {
        $nonAdminUserId = (string) \Illuminate\Support\Str::uuid();
        $forbiddenToken = $this->getTokenForUser($nonAdminUserId, [
            ['feature' => 'user', 'view' => false]
        ]);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/user/export/pdf');
        $request = $request->withHeader('Authorization', 'Bearer ' . $forbiddenToken);

        $response = $this->app->handle($request);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testExportPdfAuthorized(): void
    {
        $mockStream = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $mockStream->method('__toString')->willReturn('%PDF-1.4 test user export');

        $this->pdfProviderMock->expects($this->once())
            ->method('generatePdf')
            ->willReturn($mockStream);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/v1/user/export/pdf');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment; filename="usuarios.pdf"', $response->getHeaderLine('Content-Disposition'));
        $this->assertEquals('%PDF-1.4 test user export', (string) $response->getBody());
    }
}
