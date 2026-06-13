<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$classes = [
    // Core
    \App\Core\BaseController::class,
    \App\Core\BaseModel::class,
    \App\Core\BaseRepository::class,
    \App\Core\BaseService::class,
    \App\Core\DatabaseBootstrap::class,
    \App\Core\Exceptions\BadRequestException::class,
    \App\Core\Exceptions\ValidationException::class,
    \App\Core\Traits\ValidatableTrait::class,
    \App\Core\Transformers\BaseTransformer::class,
    \App\Core\Transformers\UserTransformer::class,
    \App\Core\Transformers\RoleTransformer::class,
    \App\Core\Transformers\FeatureTransformer::class,
    \App\Core\Helpers\QueryParserHelper::class,
    \App\Core\Helpers\QueryApplierHelper::class,

    // Infrastructure
    \App\Infrastructure\Auth\JwtService::class,
    \App\Infrastructure\Auth\UserSession::class,
    \App\Infrastructure\Database\DatabaseProvider::class,
    \App\Infrastructure\Log\RequestIdProcessor::class,

    // Middleware
    \App\Middleware\AuthMiddleware::class,
    \App\Middleware\CorsMiddleware::class,
    \App\Middleware\JsonErrorMiddleware::class,
    \App\Middleware\LogMiddleware::class,
    \App\Middleware\PermissionMiddleware::class,
    \App\Middleware\RateLimitMiddleware::class,
    \App\Middleware\TrailingSlashMiddleware::class,

    // Modules - Auth
    \App\Modules\Auth\AuthController::class,
    \App\Modules\Auth\AuthService::class,

    // Modules - User
    \App\Modules\User\User::class,
    \App\Modules\User\UserAuth::class,
    \App\Modules\User\UserRepository::class,

    // Modules - Role
    \App\Modules\Role\Role::class,
    \App\Modules\Role\RoleRepository::class,

    // Modules - Feature
    \App\Modules\Feature\Feature::class,
    \App\Modules\Feature\FeatureRepository::class,

    // Modules - Others
    \App\Modules\Health\HealthController::class,
];

foreach ($classes as $class) {
    if (class_exists($class, false)) {
        continue;
    }
    try {
        $ref = new \ReflectionClass($class);
        $file = $ref->getFileName();
        if ($file && file_exists($file)) {
            require $file;
        }
    } catch (\Throwable $e) {
        // Skip if class cannot be loaded
    }
}
