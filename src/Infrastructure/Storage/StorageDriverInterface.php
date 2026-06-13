<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

interface StorageDriverInterface
{
    public function put(string $path, string $contents): void;

    public function get(string $path): string;

    public function delete(string $path): void;

    public function exists(string $path): bool;

    public function getUrl(string $path): string;
}
