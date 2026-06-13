<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Pdf\RemotePdfProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\StreamInterface;

class RemotePdfProviderTest extends TestCase
{
    private $loggerMock;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(LoggerInterface::class);
    }

    public function testGeneratePdfSuccess(): void
    {
        $mockBody = $this->createMock(StreamInterface::class);
        $mockHandler = new MockHandler([
            new Response(200, [], $mockBody),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new RemotePdfProvider($this->loggerMock, $client);
        
        $data = [
            'template' => 'test-template',
            'data' => ['foo' => 'bar']
        ];

        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Generating PDF'));

        $result = $provider->generatePdf($data);

        $this->assertSame($mockBody, $result);
    }

    public function testGeneratePdfWithMissingTemplate(): void
    {
        $mockBody = $this->createMock(StreamInterface::class);
        $mockHandler = new MockHandler([
            new Response(200, [], $mockBody),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new RemotePdfProvider($this->loggerMock, $client);
        
        $data = ['data' => ['foo' => 'bar']]; // Missing template

        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Generating PDF'), $this->callback(function($context) {
                return $context['template'] === 'unknown';
            }));

        $result = $provider->generatePdf($data);

        $this->assertSame($mockBody, $result);
    }

    public function testGeneratePdfFallback(): void
    {
        $mockHandler = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException('Connection failed', new \GuzzleHttp\Psr7\Request('POST', '/v1/pdf/generate')),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new RemotePdfProvider($this->loggerMock, $client);

        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Remote PDF service failed'));

        $result = $provider->generatePdf(['template' => 'test']);
        $this->assertInstanceOf(StreamInterface::class, $result);
        $this->assertStringContainsString('Fallback PDF Content', $result->getContents());
    }

    public function testDefaultClientInitialization(): void
    {
        $provider = new RemotePdfProvider($this->loggerMock);
        
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        
        $this->assertInstanceOf(Client::class, $property->getValue($provider));
    }

    public function testCircuitBreakerOpensAfterThreeFailures(): void
    {
        $mockHandler = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException('Fail 1', new \GuzzleHttp\Psr7\Request('POST', '/v1/pdf/generate')),
            new \GuzzleHttp\Exception\ConnectException('Fail 2', new \GuzzleHttp\Psr7\Request('POST', '/v1/pdf/generate')),
            new \GuzzleHttp\Exception\ConnectException('Fail 3', new \GuzzleHttp\Psr7\Request('POST', '/v1/pdf/generate')),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new RemotePdfProvider($this->loggerMock, $client);

        // 3 failures: each logs warning. 3rd failure also logs circuit opened.
        // 4th call: circuit breaker open, logs warning.
        // Total: 5 warnings
        $this->loggerMock->expects($this->exactly(5))
            ->method('warning');

        for ($i = 0; $i < 3; $i++) {
            $result = $provider->generatePdf(['template' => 'test']);
            $this->assertStringContainsString('Fallback PDF Content', $result->getContents());
        }

        $result = $provider->generatePdf(['template' => 'test']);
        $this->assertStringContainsString('Fallback PDF Content', $result->getContents());
    }

    public function testCircuitBreakerResetsAfterTimeout(): void
    {
        $mockBody = $this->createMock(StreamInterface::class);
        $mockHandler = new MockHandler([
            new \GuzzleHttp\Exception\ConnectException('Fail', new \GuzzleHttp\Psr7\Request('POST', '/v1/pdf/generate')),
            new \GuzzleHttp\Exception\ConnectException('Fail', new \GuzzleHttp\Psr7\Request('POST', '/v1/pdf/generate')),
            new \GuzzleHttp\Exception\ConnectException('Fail', new \GuzzleHttp\Psr7\Request('POST', '/v1/pdf/generate')),
            new Response(200, [], $mockBody),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new RemotePdfProvider($this->loggerMock, $client);

        for ($i = 0; $i < 3; $i++) {
            $provider->generatePdf(['template' => 'test']);
        }

        $reflection = new \ReflectionClass($provider);
        $cb = $reflection->getProperty('circuitBreaker');
        $cb->setAccessible(true);
        $cbValue = $cb->getValue($provider);
        $cbValue['opened_at'] = microtime(true) - 31;
        $cb->setValue($provider, $cbValue);

        $result = $provider->generatePdf(['template' => 'test']);
        $this->assertSame($mockBody, $result);
    }
}
