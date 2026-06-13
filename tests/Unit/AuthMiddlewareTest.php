<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Auth\JwtService;
use App\Infrastructure\Auth\UserSession;
use App\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpUnauthorizedException;

class AuthMiddlewareTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&JwtService */
    private $jwtServiceMock;
    private AuthMiddleware $middleware;
    /** @var \PHPUnit\Framework\MockObject\MockObject&ServerRequestInterface */
    private $requestMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&RequestHandlerInterface */
    private $handlerMock;

    protected function setUp(): void
    {
        $this->jwtServiceMock = $this->createMock(JwtService::class);
        $userSession = new UserSession();
        $this->middleware = new AuthMiddleware($this->jwtServiceMock, $userSession);
        $this->requestMock = $this->createMock(ServerRequestInterface::class);
        $this->handlerMock = $this->createMock(RequestHandlerInterface::class);
    }

    public function testProcessWithValidToken(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer valid-token');

        $claims = ['uid' => 'user-123', 'name' => 'John Doe'];
        $this->jwtServiceMock->expects($this->once())
            ->method('validateToken')
            ->with('valid-token')
            ->willReturn($claims);

        $this->jwtServiceMock->expects($this->once())
            ->method('isTokenValid')
            ->with('user-123', 'valid-token')
            ->willReturn(true);

        $this->requestMock->expects($this->exactly(2))
            ->method('withAttribute')
            ->willReturnSelf();

        $responseMock = $this->createMock(ResponseInterface::class);
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->willReturn($responseMock);

        $result = $this->middleware->process($this->requestMock, $this->handlerMock);
        $this->assertSame($responseMock, $result);
    }

    public function testProcessMissingHeader(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getHeaderLine')
            ->willReturn('');

        $this->expectException(HttpUnauthorizedException::class);
        $this->expectExceptionMessage('Authorization token missing');

        $this->middleware->process($this->requestMock, $this->handlerMock);
    }

    public function testProcessInvalidHeaderFormat(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getHeaderLine')
            ->willReturn('InvalidFormat token');

        $this->expectException(HttpUnauthorizedException::class);
        $this->expectExceptionMessage('Authorization token missing');

        $this->middleware->process($this->requestMock, $this->handlerMock);
    }

    public function testProcessInvalidToken(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getHeaderLine')
            ->willReturn('Bearer invalid-token');

        $this->jwtServiceMock->expects($this->once())
            ->method('validateToken')
            ->willReturn(null);

        $this->expectException(HttpUnauthorizedException::class);
        $this->expectExceptionMessage('Invalid or expired token');

        $this->middleware->process($this->requestMock, $this->handlerMock);
    }

    public function testProcessWithRevokedSession(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getHeaderLine')
            ->willReturn('Bearer valid-token');

        $claims = ['uid' => 'user-123'];
        $this->jwtServiceMock->expects($this->once())
            ->method('validateToken')
            ->willReturn($claims);

        $this->jwtServiceMock->expects($this->once())
            ->method('isTokenValid')
            ->with('user-123', 'valid-token')
            ->willReturn(false);

        $this->expectException(HttpUnauthorizedException::class);
        $this->expectExceptionMessage('Session revoked or invalid');

        $this->middleware->process($this->requestMock, $this->handlerMock);
    }

    public function testProcessWithMissingUidInClaims(): void
    {
        $this->requestMock->expects($this->once())
            ->method('getHeaderLine')
            ->willReturn('Bearer valid-token');

        $claims = ['name' => 'No UID'];
        $this->jwtServiceMock->expects($this->once())
            ->method('validateToken')
            ->willReturn($claims);

        $this->expectException(HttpUnauthorizedException::class);
        $this->expectExceptionMessage('Session revoked or invalid');

        $this->middleware->process($this->requestMock, $this->handlerMock);
    }
}
