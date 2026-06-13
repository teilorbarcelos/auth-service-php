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
    \App\Core\Transformers\ProductTransformer::class,
    \App\Core\Transformers\FeatureTransformer::class,
    \App\Core\Helpers\QueryParserHelper::class,
    \App\Core\Helpers\QueryApplierHelper::class,

    // Infrastructure
    \App\Infrastructure\Auth\JwtService::class,
    \App\Infrastructure\Auth\UserSession::class,
    \App\Infrastructure\Database\DatabaseProvider::class,
    \App\Infrastructure\Email\EmailProvider::class,
    \App\Infrastructure\Email\EmailTemplates::class,
    \App\Infrastructure\Log\RequestIdProcessor::class,
    \App\Infrastructure\Messaging\RabbitMQProvider::class,
    \App\Infrastructure\Metrics\MetricService::class,
    \App\Infrastructure\Pdf\PdfProviderInterface::class,
    \App\Infrastructure\Pdf\RemotePdfProvider::class,
    \App\Infrastructure\Storage\StorageProvider::class,
    \App\Infrastructure\Storage\Drivers\LocalDriver::class,

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
    \App\Modules\User\UserController::class,
    \App\Modules\User\UserService::class,
    \App\Modules\User\UserRepository::class,

    // Modules - Role
    \App\Modules\Role\Role::class,
    \App\Modules\Role\RoleController::class,
    \App\Modules\Role\RoleService::class,
    \App\Modules\Role\RoleRepository::class,

    // Modules - Product
    \App\Modules\Product\Product::class,
    \App\Modules\Product\ProductController::class,
    \App\Modules\Product\ProductService::class,
    \App\Modules\Product\ProductRepository::class,

    // Modules - Feature
    \App\Modules\Feature\Feature::class,
    \App\Modules\Feature\FeatureController::class,
    \App\Modules\Feature\FeatureService::class,
    \App\Modules\Feature\FeatureRepository::class,

    // Modules - Others
    \App\Modules\Audit\Audit::class,
    \App\Modules\Audit\ErrorLog::class,
    \App\Modules\Audit\AuditObserver::class,
    \App\Modules\Dashboard\DashboardController::class,
    \App\Modules\Dashboard\DashboardService::class,
    \App\Modules\Health\HealthController::class,
    \App\Modules\Metrics\MetricsController::class,
];

foreach ($classes as $class) {
    if (class_exists($class, false)) {
        continue;
    }
    try {
        $ref = new \ReflectionClass($class);
        // Trigger autoloader by getting file name
        $file = $ref->getFileName();
        if ($file && file_exists($file)) {
            require $file;
        }
    } catch (\Throwable $e) {
        // Skip if class cannot be loaded
    }
}
