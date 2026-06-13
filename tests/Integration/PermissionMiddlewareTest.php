<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Middleware\PermissionMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Tests\WebTestCase;
use Psr\Http\Message\ResponseInterface;

class PermissionMiddlewareTest extends WebTestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&ServerRequestInterface */
    private $requestMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&RequestHandlerInterface */
    private $handlerMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestMock = $this->createMock(ServerRequestInterface::class);
        $this->handlerMock = $this->createMock(RequestHandlerInterface::class);
    }

    public function testAllowsAccessWhenUserHasPermission(): void
    {
        $this->requestMock->method('getAttribute')
            ->with('user')
            ->willReturn([
                'uid' => '1',
                'permissions' => [
                    ['feature' => 'product', 'view' => true]
                ]
            ]);

        $middleware = new PermissionMiddleware('product', 'view');

        $response = $this->createMock(ResponseInterface::class);
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->willReturn($response);

        $result = $middleware->process($this->requestMock, $this->handlerMock);
        $this->assertSame($response, $result);
    }

    public function testDeniesAccessWhenUserLacksPermission(): void
    {
        $this->requestMock->method('getAttribute')
            ->with('user')
            ->willReturn([
                'uid' => '1',
                'permissions' => [
                    ['feature' => 'product', 'view' => false]
                ]
            ]);

        $middleware = new PermissionMiddleware('product', 'view');

        $this->expectException(HttpForbiddenException::class);
        $this->expectExceptionMessage("Sem permissão para view em product");

        $middleware->process($this->requestMock, $this->handlerMock);
    }

    public function testDeniesAccessWhenFeatureIsMissing(): void
    {
        $this->requestMock->method('getAttribute')
            ->with('user')
            ->willReturn([
                'uid' => '1',
                'permissions' => [
                    ['feature' => 'user', 'view' => true]
                ]
            ]);

        $middleware = new PermissionMiddleware('product', 'view');

        $this->expectException(HttpForbiddenException::class);
        $middleware->process($this->requestMock, $this->handlerMock);
    }

    public function testDeniesAccessWhenNoUserSession(): void
    {
        $this->requestMock->method('getAttribute')
            ->with('user')
            ->willReturn(null);

        $middleware = new PermissionMiddleware('product', 'view');

        $this->expectException(HttpForbiddenException::class);
        $middleware->process($this->requestMock, $this->handlerMock);
    }

    public function testAdminBypassesPermissionCheck(): void
    {
        $this->requestMock->method('getAttribute')
            ->with('user')
            ->willReturn([
                'uid' => 'admin-1',
                'id_role' => 'administrator',
                'permissions' => []
            ]);

        $middleware = new PermissionMiddleware('product', 'view');

        $response = $this->createMock(ResponseInterface::class);
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->willReturn($response);

        $result = $middleware->process($this->requestMock, $this->handlerMock);
        $this->assertSame($response, $result);
    }
}
