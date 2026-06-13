<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\SwaggerController;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class SwaggerControllerTest extends TestCase
{
    private SwaggerController $controller;
    private ServerRequestFactory $requestFactory;
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->controller = new SwaggerController();
        $this->requestFactory = new ServerRequestFactory();
        $this->responseFactory = new ResponseFactory();
    }

    public function testJson(): void
    {
        $request = $this->requestFactory->createServerRequest('GET', '/swagger.json');
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->json($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('application/json', $result->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('openapi', (string)$result->getBody());
    }

    public function testUi(): void
    {
        $request = $this->requestFactory->createServerRequest('GET', '/docs');
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->ui($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('text/html', $result->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('<div id="swagger-ui"></div>', (string)$result->getBody());
    }

    public function testJsonError(): void
    {
        SwaggerController::$forceError = true;
        
        $request = $this->requestFactory->createServerRequest('GET', '/swagger.json');
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->json($request, $response);
        
        $this->assertEquals(500, $result->getStatusCode());
        
        SwaggerController::$forceError = false;
    }

    public function testJsonFallbackUrl(): void
    {
        $originalUrl = getenv('APP_URL');
        putenv('APP_URL');
        unset($_ENV['APP_URL']);
        unset($_SERVER['APP_URL']);
        
        $request = $this->requestFactory->createServerRequest('GET', '/swagger.json');
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->json($request, $response);
        
        $this->assertEquals(200, $result->getStatusCode());
        
        if ($originalUrl !== false) {
            putenv("APP_URL=$originalUrl");
            $_ENV['APP_URL'] = $originalUrl;
        }
    }
}
