<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\BaseController;
use App\Core\BaseService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface;

class BaseControllerTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&BaseService<\Illuminate\Database\Eloquent\Model, \App\Core\BaseRepository<\Illuminate\Database\Eloquent\Model>> */
    private $serviceMock;
    private BaseController $controller;
    private ServerRequestFactory $requestFactory;
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->serviceMock = $this->createMock(BaseService::class);
        $this->requestFactory = new ServerRequestFactory();
        $this->responseFactory = new ResponseFactory();

        // Concrete implementation for testing
        $this->controller = new class($this->serviceMock) extends BaseController {
            /** @param BaseService<\Illuminate\Database\Eloquent\Model, \App\Core\BaseRepository<\Illuminate\Database\Eloquent\Model>> $service */
            public function __construct(BaseService $service) {
                $this->service = $service;
            }
        };
    }

    public function testListItems(): void
    {
        $request = $this->requestFactory->createServerRequest('GET', '/');
        $response = $this->responseFactory->createResponse();

        $this->serviceMock->expects($this->once())
            ->method('listItems')
            ->willReturn(['items' => [], 'total' => 0]);

        $result = $this->controller->listItems($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertStringContainsString('application/json', $result->getHeaderLine('Content-Type'));
    }

    public function testListAllItems(): void
    {
        $request = $this->requestFactory->createServerRequest('GET', '/all');
        $response = $this->responseFactory->createResponse();

        $this->serviceMock->expects($this->once())
            ->method('listAllItems')
            ->willReturn([]);

        $result = $this->controller->listAllItems($request, $response);
        $this->assertEquals(200, $result->getStatusCode());
    }

    public function testGetByIdSuccess(): void
    {
        $request = $this->requestFactory->createServerRequest('GET', '/123');
        $response = $this->responseFactory->createResponse();

        $this->serviceMock->expects($this->once())
            ->method('retrieveById')
            ->with('123')
            ->willReturn(['id' => '123']);

        $result = $this->controller->getById($request, $response, ['id' => '123']);
        $this->assertEquals(200, $result->getStatusCode());
    }

    public function testGetByIdNotFound(): void
    {
        $request = $this->requestFactory->createServerRequest('GET', '/123');
        $response = $this->responseFactory->createResponse();

        $this->serviceMock->expects($this->once())
            ->method('retrieveById')
            ->willReturn(null);

        $result = $this->controller->getById($request, $response, ['id' => '123']);
        $this->assertEquals(404, $result->getStatusCode());
    }

    public function testCreate(): void
    {
        $request = $this->requestFactory->createServerRequest('POST', '/')
            ->withParsedBody(['name' => 'Test']);
        $response = $this->responseFactory->createResponse();

        $this->serviceMock->expects($this->once())
            ->method('create')
            ->willReturn(['id' => '1', 'name' => 'Test']);

        $result = $this->controller->create($request, $response);
        $this->assertEquals(201, $result->getStatusCode());
    }

    public function testUpdateSuccess(): void
    {
        $request = $this->requestFactory->createServerRequest('PUT', '/123')
            ->withParsedBody(['name' => 'Updated']);
        $response = $this->responseFactory->createResponse();

        $this->serviceMock->expects($this->once())
            ->method('update')
            ->willReturn(['id' => '123', 'name' => 'Updated']);

        $result = $this->controller->update($request, $response, ['id' => '123']);
        $this->assertEquals(200, $result->getStatusCode());
    }

    public function testDeleteSuccess(): void
    {
        $request = $this->requestFactory->createServerRequest('DELETE', '/123');
        $response = $this->responseFactory->createResponse();

        $this->serviceMock->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $result = $this->controller->delete($request, $response, ['id' => '123']);
        $this->assertEquals(204, $result->getStatusCode());
    }

    public function testToggleStatus(): void
    {
        $request = $this->requestFactory->createServerRequest('PATCH', '/123/status')
            ->withParsedBody(['active' => false]);
        $response = $this->responseFactory->createResponse();

        $this->serviceMock->expects($this->once())
            ->method('setStatus')
            ->with('123', false)
            ->willReturn(['id' => '123', 'active' => false]);

        $result = $this->controller->toggleStatus($request, $response, ['id' => '123']);
        $this->assertEquals(200, $result->getStatusCode());
    }

    public function testUpdateNotFound(): void
    {
        $request = $this->requestFactory->createServerRequest('PUT', '/123')
            ->withParsedBody(['name' => 'Updated']);
        $response = $this->responseFactory->createResponse();

        $this->serviceMock->expects($this->once())
            ->method('update')
            ->willReturn(null);

        $result = $this->controller->update($request, $response, ['id' => '123']);
        $this->assertEquals(404, $result->getStatusCode());
    }

    public function testDeleteNotFound(): void
    {
        $request = $this->requestFactory->createServerRequest('DELETE', '/123');
        $response = $this->responseFactory->createResponse();

        $this->serviceMock->expects($this->once())
            ->method('delete')
            ->willReturn(false);

        $result = $this->controller->delete($request, $response, ['id' => '123']);
        $this->assertEquals(404, $result->getStatusCode());
    }

    public function testToggleStatusNotFound(): void
    {
        $request = $this->requestFactory->createServerRequest('PATCH', '/123/status')
            ->withParsedBody(['active' => false]);
        $response = $this->responseFactory->createResponse();

        $this->serviceMock->expects($this->once())
            ->method('setStatus')
            ->willReturn(null);

        $result = $this->controller->toggleStatus($request, $response, ['id' => '123']);
        $this->assertEquals(404, $result->getStatusCode());
    }
}
