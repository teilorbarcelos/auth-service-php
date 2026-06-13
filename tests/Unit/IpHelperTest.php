<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Helpers\IpHelper;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class IpHelperTest extends TestCase
{
    public function testGetClientIpReturnsForwardedFor(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getServerParams')
            ->willReturn(['HTTP_X_FORWARDED_FOR' => '192.168.1.1']);

        $this->assertEquals('192.168.1.1', IpHelper::getClientIp($request));
    }

    public function testGetClientIpReturnsRemoteAddr(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getServerParams')
            ->willReturn(['REMOTE_ADDR' => '10.0.0.1']);

        $this->assertEquals('10.0.0.1', IpHelper::getClientIp($request));
    }

    public function testGetClientIpReturnsUnknown(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getServerParams')
            ->willReturn([]);

        $this->assertEquals('unknown', IpHelper::getClientIp($request));
    }

    public function testGetClientIpPrefersForwardedFor(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getServerParams')
            ->willReturn([
                'HTTP_X_FORWARDED_FOR' => '203.0.113.1',
                'REMOTE_ADDR' => '10.0.0.1'
            ]);

        $this->assertEquals('203.0.113.1', IpHelper::getClientIp($request));
    }
}
