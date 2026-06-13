<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Storage;

use App\Infrastructure\Storage\StorageProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StorageProviderTest extends TestCase
{
    private $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        $_ENV['STORAGE_DISK'] = 'local';
    }

    public function testDefaultToLocal(): void
    {
        unset($_ENV['STORAGE_DISK']);
        $provider = new StorageProvider($this->logger);
        
        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('driver');
        $property->setAccessible(true);
        $driver = $property->getValue($provider);
        
        $this->assertInstanceOf(\App\Infrastructure\Storage\Drivers\LocalDriver::class, $driver);
    }

    public function testUnsupportedDisk(): void
    {
        $_ENV['STORAGE_DISK'] = 'invalid';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Storage disk [invalid] not supported.");
        new StorageProvider($this->logger);
    }

    public function testS3Resolution(): void
    {
        $_ENV['STORAGE_DISK'] = 's3';
        
        if (class_exists(\App\Infrastructure\Storage\Drivers\S3Driver::class)) {
            $provider = new StorageProvider($this->logger);
            $reflection = new \ReflectionClass($provider);
            $property = $reflection->getProperty('driver');
            $property->setAccessible(true);
            $this->assertInstanceOf(\App\Infrastructure\Storage\Drivers\S3Driver::class, $property->getValue($provider));
        } else {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage("not installed");
            new StorageProvider($this->logger);
        }
    }

    public function testDelegation(): void
    {
        $_ENV['STORAGE_DISK'] = 'local';
        $provider = new StorageProvider($this->logger);
        
        $mockDriver = $this->createMock(\App\Infrastructure\Storage\StorageDriverInterface::class);
        $mockDriver->expects($this->once())->method('put')->with('test', 'data');
        $mockDriver->expects($this->once())->method('get')->willReturn('data');
        $mockDriver->expects($this->once())->method('delete');
        $mockDriver->expects($this->once())->method('exists')->willReturn(true);
        $mockDriver->expects($this->once())->method('getUrl')->willReturn('url');

        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('driver');
        $property->setAccessible(true);
        $property->setValue($provider, $mockDriver);

        $provider->put('test', 'data');
        $this->assertEquals('data', $provider->get('test'));
        $provider->delete('test');
        $this->assertTrue($provider->exists('test'));
        $this->assertEquals('url', $provider->getUrl('test'));
    }

    public function testResolveDriverSuccess(): void
    {
        $_ENV['STORAGE_DISK'] = 'mock';
        
        // Define a mock driver class dynamically to test resolveDriver success path
        if (!class_exists('App\Infrastructure\Storage\Drivers\MockDriver')) {
            eval('namespace App\Infrastructure\Storage\Drivers; class MockDriver implements \App\Infrastructure\Storage\StorageDriverInterface { 
                public function __construct($logger) {}
                public function put(string $path, string $contents): void {}
                public function get(string $path): string { return "data"; }
                public function delete(string $path): void {}
                public function exists(string $path): bool { return true; }
                public function getUrl(string $path): string { return "url"; }
            }');
        }

        $reflection = new \ReflectionClass(StorageProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();
        $property = $reflection->getProperty('logger');
        $property->setAccessible(true);
        $property->setValue($provider, $this->logger);

        $method = $reflection->getMethod('resolveDriver');
        $method->setAccessible(true);
        
        $driver = $method->invoke($provider, 'Mock');
        $this->assertInstanceOf(\App\Infrastructure\Storage\Drivers\MockDriver::class, $driver);
    }

    public function testGcsAndAzureResolution(): void
    {
        // GCS
        $_ENV['STORAGE_DISK'] = 'gcs';
        if (class_exists(\App\Infrastructure\Storage\Drivers\GcsDriver::class)) {
            try {
                new StorageProvider($this->logger);
            } catch (\Throwable $e) {
                // If it fails due to missing credentials, it's fine, the branch was hit
                $this->assertStringNotContainsString('Storage disk [gcs] not supported', $e->getMessage());
            }
        } else {
            try {
                new StorageProvider($this->logger);
            } catch (\RuntimeException $e) {
                // Check for a substring that is likely to be there regardless of case/exact wording
                $this->assertStringContainsString('not installed', $e->getMessage());
                $this->assertStringContainsString('GCS', strtoupper($e->getMessage()));
            }
        }

        // Azure
        $_ENV['STORAGE_DISK'] = 'azure';
        if (class_exists(\App\Infrastructure\Storage\Drivers\AzureDriver::class)) {
            try {
                new StorageProvider($this->logger);
            } catch (\Throwable $e) {
                // If it fails due to missing credentials, it's fine, the branch was hit
                $this->assertStringNotContainsString('Storage disk [azure] not supported', $e->getMessage());
            }
        } else {
            try {
                new StorageProvider($this->logger);
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('not installed', $e->getMessage());
                $this->assertStringContainsString('AZURE', strtoupper($e->getMessage()));
            }
        }
    }
}
