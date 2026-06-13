<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use Psr\Log\LoggerInterface;

class StorageProvider implements StorageDriverInterface
{
    private StorageDriverInterface $driver;

    public function __construct(private LoggerInterface $logger)
    {
        $disk = $_ENV['STORAGE_DISK'] ?? 'local';

        $this->driver = match ($disk) {
            'local' => new Drivers\LocalDriver($this->logger),
            's3' => $this->resolveDriver('S3'),
            'gcs' => $this->resolveDriver('GCS'),
            'azure' => $this->resolveDriver('Azure'),
            default => throw new \RuntimeException("Storage disk [{$disk}] not supported.")
        };
    }

    private function resolveDriver(string $name): StorageDriverInterface
    {
        $className = "App\\Infrastructure\\Storage\\Drivers\\{$name}Driver";

        if (!class_exists($className)) {
            $driverName = strtolower($name);
            throw new \RuntimeException(
                "Driver [{$name}] is not installed or generated. " .
                "Please run 'make storage-driver name={$driverName}' to set it up."
            );
        }

        /** @var StorageDriverInterface $driver */
        $driver = new $className($this->logger);
        return $driver;
    }

    public function put(string $path, string $contents): void
    {
        $this->driver->put($path, $contents);
    }

    public function get(string $path): string
    {
        return $this->driver->get($path);
    }

    public function delete(string $path): void
    {
        $this->driver->delete($path);
    }

    public function exists(string $path): bool
    {
        return $this->driver->exists($path);
    }

    public function getUrl(string $path): string
    {
        return $this->driver->getUrl($path);
    }
}
