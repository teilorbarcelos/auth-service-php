<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Storage\Drivers;

use App\Infrastructure\Storage\Drivers\LocalDriver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\Filesystem;

class LocalDriverTest extends TestCase
{
    private $logger;

    protected function setUp(): void
    {
        $_ENV['STORAGE_DISK'] = 'local';
        $_ENV['STORAGE_URL'] = 'http://localhost/storage';
        $_ENV['STORAGE_PATH'] = '/tmp/storage';
        
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testPutAndGet(): void
    {
        $mockFilesystem = $this->createMock(Filesystem::class);
        $mockFilesystem->method('write');
        $mockFilesystem->method('read')->willReturn('hello world');
        $mockFilesystem->method('fileExists')->willReturn(true);

        $driver = new LocalDriver($this->logger, $mockFilesystem);
        
        $driver->put('test.txt', 'hello world');
        $this->assertTrue($driver->exists('test.txt'));
        $this->assertEquals('hello world', $driver->get('test.txt'));
    }

    public function testDelete(): void
    {
        $mockFilesystem = $this->createMock(Filesystem::class);
        $mockFilesystem->method('delete');
        $mockFilesystem->method('fileExists')->willReturn(false);

        $driver = new LocalDriver($this->logger, $mockFilesystem);
        
        $driver->delete('delete_me.txt');
        $this->assertFalse($driver->exists('delete_me.txt'));
    }

    public function testGetUrl(): void
    {
        $mockFilesystem = $this->createMock(Filesystem::class);
        $driver = new LocalDriver($this->logger, $mockFilesystem);
        $this->assertEquals('http://localhost/storage/path/to/file.png', $driver->getUrl('path/to/file.png'));
    }

    public function testPutError(): void
    {
        $mockFilesystem = $this->createMock(Filesystem::class);
        $mockFilesystem->method('write')->willThrowException(
            new \League\Flysystem\UnableToWriteFile('Error writing')
        );

        $driver = new LocalDriver($this->logger, $mockFilesystem);
        
        $this->logger->expects($this->once())->method('error')->with($this->stringContains("Failed to write file"));
        
        $this->expectException(FilesystemException::class);
        $driver->put('error.txt', 'data');
    }

    public function testGetError(): void
    {
        $mockFilesystem = $this->createMock(Filesystem::class);
        $mockFilesystem->method('read')->willThrowException(
            new \League\Flysystem\UnableToReadFile('Error reading')
        );

        $driver = new LocalDriver($this->logger, $mockFilesystem);
        
        $this->logger->expects($this->once())->method('error')->with($this->stringContains("Failed to read file"));
        
        $this->expectException(FilesystemException::class);
        $driver->get('non_existent.txt');
    }

    public function testDeleteError(): void
    {
        $mockFilesystem = $this->createMock(Filesystem::class);
        $mockFilesystem->method('delete')->willThrowException(
            new \League\Flysystem\UnableToDeleteFile('Error deleting')
        );

        $driver = new LocalDriver($this->logger, $mockFilesystem);
        
        $this->logger->expects($this->once())->method('error')->with($this->stringContains("Failed to delete file"));
        
        $this->expectException(FilesystemException::class);
        $driver->delete('non_existent.txt');
    }
}
