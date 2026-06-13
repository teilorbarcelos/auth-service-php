<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Middleware\BodySizeLimitMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class BodySizeLimitMiddlewareTest extends TestCase
{
    public function testPassesRequestUnderLimit(): void
    {
        $middleware = new BodySizeLimitMiddleware(1024);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Length')->willReturn('512');

        $expected = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn($expected);

        $this->assertSame($expected, $middleware->process($request, $handler));
    }

    public function testRejectsRequestOverLimit(): void
    {
        $middleware = new BodySizeLimitMiddleware(1024);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Length')->willReturn('2048');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $middleware->process($request, $handler);
        $this->assertSame(413, $response->getStatusCode());
    }

    public function testAllowsRequestWithoutContentLength(): void
    {
        $middleware = new BodySizeLimitMiddleware(1024);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')->with('Content-Length')->willReturn('');

        $expected = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn($expected);

        $this->assertSame($expected, $middleware->process($request, $handler));
    }

    public function testParsesMegabytes(): void
    {
        $middleware = new BodySizeLimitMiddleware(2 * 1024 * 1024);
        $ref = new \ReflectionClass($middleware);
        $prop = $ref->getProperty('maxBytes');
        $prop->setAccessible(true);
        $this->assertSame(2 * 1024 * 1024, $prop->getValue($middleware));
    }

    public function testParsesEnvValueInMegabytes(): void
    {
        $original = $_ENV['REQUEST_BODY_MAX_SIZE'] ?? null;
        $_ENV['REQUEST_BODY_MAX_SIZE'] = '5M';
        try {
            $middleware = new BodySizeLimitMiddleware();
            $ref = new \ReflectionClass($middleware);
            $prop = $ref->getProperty('maxBytes');
            $prop->setAccessible(true);
            $this->assertSame(5 * 1024 * 1024, $prop->getValue($middleware));
        } finally {
            if ($original === null) {
                unset($_ENV['REQUEST_BODY_MAX_SIZE']);
            } else {
                $_ENV['REQUEST_BODY_MAX_SIZE'] = $original;
            }
        }
    }

    public function testParsesSizeInKilobytes(): void
    {
        $middleware = new BodySizeLimitMiddleware(1024);
        $ref = new \ReflectionClass($middleware);
        $prop = $ref->getProperty('maxBytes');
        $prop->setAccessible(true);
        $this->assertSame(1024, $prop->getValue($middleware));
    }

    public function testParsesEnvValueInGigabytes(): void
    {
        $original = $_ENV['REQUEST_BODY_MAX_SIZE'] ?? null;
        $_ENV['REQUEST_BODY_MAX_SIZE'] = '2G';
        try {
            $middleware = new BodySizeLimitMiddleware();
            $ref = new \ReflectionClass($middleware);
            $prop = $ref->getProperty('maxBytes');
            $prop->setAccessible(true);
            $this->assertSame(2 * 1024 * 1024 * 1024, $prop->getValue($middleware));
        } finally {
            if ($original === null) {
                unset($_ENV['REQUEST_BODY_MAX_SIZE']);
            } else {
                $_ENV['REQUEST_BODY_MAX_SIZE'] = $original;
            }
        }
    }

    public function testParsesEnvValueInKilobytes(): void
    {
        $original = $_ENV['REQUEST_BODY_MAX_SIZE'] ?? null;
        $_ENV['REQUEST_BODY_MAX_SIZE'] = '512K';
        try {
            $middleware = new BodySizeLimitMiddleware();
            $ref = new \ReflectionClass($middleware);
            $prop = $ref->getProperty('maxBytes');
            $prop->setAccessible(true);
            $this->assertSame(512 * 1024, $prop->getValue($middleware));
        } finally {
            if ($original === null) {
                unset($_ENV['REQUEST_BODY_MAX_SIZE']);
            } else {
                $_ENV['REQUEST_BODY_MAX_SIZE'] = $original;
            }
        }
    }
}
