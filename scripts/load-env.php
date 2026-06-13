<?php

declare(strict_types=1);

/**
 * Load .env with createImmutable (safe) and populate $_ENV.
 * Call this at the entry point of any script that needs env vars.
 */
function loadEnvironment(string $dir): void
{
    $file = rtrim($dir, '/') . '/.env';
    if (!file_exists($file)) {
        return;
    }

    $dotenv = Dotenv\Dotenv::createImmutable($dir);
    $variables = $dotenv->load();

    foreach ($variables as $key => $value) {
        $_ENV[$key] = $value;
    }
}
