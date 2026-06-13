<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\Drivers;

use App\Infrastructure\Storage\StorageDriverInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\{{ADAPTER_CLASS}};
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
{{EXTRA_USES}}

class {{DRIVER_NAME}}Driver implements StorageDriverInterface
{
    private Filesystem $filesystem;
    private string $baseUrl;

    public function __construct(private LoggerInterface $logger, ?Filesystem $filesystem = null)
    {
        /** @var string $storageUrl */
        $storageUrl = $_ENV['STORAGE_URL'] ?? '';
        $this->baseUrl = rtrim($storageUrl, '/');

        if ($filesystem) {
            $this->filesystem = $filesystem;
            return;
        }

        {{ADAPTER_SETUP}}

        $this->filesystem = new Filesystem($adapter);
    }

    public function put(string $path, string $contents): void
    {
        try {
            $this->filesystem->write($path, $contents);
        } catch (FilesystemException $e) {
            $this->logger->error("[Storage][{{DRIVER_NAME}}] Failed to write file to {$path}: {$e->getMessage()}");
            throw $e;
        }
    }

    public function get(string $path): string
    {
        try {
            return $this->filesystem->read($path);
        } catch (FilesystemException $e) {
            $this->logger->error("[Storage][{{DRIVER_NAME}}] Failed to read file from {$path}: {$e->getMessage()}");
            throw $e;
        }
    }

    public function delete(string $path): void
    {
        try {
            $this->filesystem->delete($path);
        } catch (FilesystemException $e) {
            $this->logger->error("[Storage][{{DRIVER_NAME}}] Failed to delete file {$path}: {$e->getMessage()}");
            throw $e;
        }
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->fileExists($path);
    }

    public function getUrl(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }
}
