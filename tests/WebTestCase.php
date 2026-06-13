<?php

declare(strict_types=1);

namespace Tests;

use App\Core\DatabaseBootstrap;
use DI\ContainerBuilder;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\RequestFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class WebTestCase extends TestCase
{
    protected App $app;
    protected static ?App $staticApp = null;

    protected function getContainer(): \Psr\Container\ContainerInterface
    {
        $container = $this->app->getContainer();
        if (!$container) {
            throw new \RuntimeException('Container not found');
        }
        return $container;
    }

    protected function setUp(): void
    {
        parent::setUp();

        \App\Infrastructure\Database\DatabaseProvider::resetInstance();

        if (self::$staticApp === null) {
            self::$staticApp = $this->createApp();
        }
        $this->app = self::$staticApp;

        // Initialize Database for testing (SQLite Memory)
        $this->setUpDatabase();

        $userSession = \App\Infrastructure\Auth\UserSession::getInstance();
        $userSession->setUser(null);
        $userSession->setUserId(null);

        DatabaseBootstrap::init();
    }

    protected function setUpDatabase(): void
    {
        $db = \Illuminate\Database\Capsule\Manager::connection();
        if ($db->getDriverName() === 'sqlite') {
            try {
                $db->statement("ATTACH DATABASE ':memory:' AS audit");
            } catch (\Exception $e) {
                // Already attached
            }
        }

        $schema = \Illuminate\Database\Capsule\Manager::schema();

        // Drop existing tables in correct order (children first)
        $schema->dropIfExists('role_features');
        $schema->dropIfExists('users');
        $schema->dropIfExists('auth');
        $schema->dropIfExists('roles');
        $schema->dropIfExists('features');
        $schema->dropIfExists('products');
        $schema->dropIfExists('audit.tb_audit');
        $schema->dropIfExists('audit.tb_error_log');

        $schema->create('roles', function ($table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('features', function ($table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('role_features', function ($table) {
            $table->string('id_role');
            $table->string('id_feature');
            $table->json('permissions')->nullable();
            $table->primary(['id_role', 'id_feature']);

            $table->foreign('id_role')->references('id')->on('roles')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('id_feature')->references('id')->on('features')->onDelete('cascade')->onUpdate('cascade');
        });

        $schema->create('users', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('id_role');
            $table->boolean('active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->foreign('id_role')->references('id')->on('roles')->onDelete('restrict')->onUpdate('cascade');
        });

        $schema->create('auth', function ($table) {
            $table->uuid('id')->primary();
            $table->string('password')->nullable();
            $table->string('request_password_token')->nullable();
            $table->timestamp('request_password_expiration')->nullable();
            $table->integer('retries')->default(0);
            $table->boolean('first_access')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $schema->create('audit.tb_audit', function ($table) {
            $table->uuid('id')->primary();
            $table->string('id_user')->nullable();
            $table->string('user_name')->nullable();
            $table->string('action_type')->nullable();
            $table->string('table_name')->nullable();
            $table->json('diff_value')->nullable();
            $table->json('raw')->nullable();
            $table->string('ip')->nullable();
            $table->string('method')->nullable();
            $table->string('original_url')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $schema->create('audit.tb_error_log', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('id_user')->nullable();
            $table->string('source')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $schema->create('products', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('id_user')->nullable();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('category')->nullable();
            $table->integer('stock')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    protected function createApp(): App
    {
        $containerBuilder = new ContainerBuilder();
        $settings = require __DIR__ . '/../config/container.php';
        $containerBuilder->addDefinitions($settings);

        $redisFake = new class extends \Redis {
            private array $storage = [];
            public function __construct()
            {
            }
            public function ping(?string $message = null): \Redis|string|bool
            {
                return 'PONG';
            }
            public function get(string $key): mixed
            {
                return $this->storage[$key] ?? null;
            }
            public function setex(string $key, int $expire, mixed $value): bool
            {
                $this->storage[$key] = $value;
                return true;
            }
            public function setnx(string $key, mixed $value): \Redis|bool
            {
                if (!isset($this->storage[$key])) {
                    $this->storage[$key] = $value;
                    return true;
                }
                return false;
            }
            public function srem(string $key, mixed $value, mixed ...$other_values): \Redis|int|false
            {
                if (isset($this->storage[$key]) && is_array($this->storage[$key])) {
                    $idx = array_search($value, $this->storage[$key], true);
                    if ($idx !== false) {
                        array_splice($this->storage[$key], $idx, 1);
                        return 1;
                    }
                }
                return 0;
            }
            public function incr(string $key, int $by = 1): \Redis|int|false
            {
                $this->storage[$key] = ($this->storage[$key] ?? 0) + $by;
                return (int) $this->storage[$key];
            }
            public function sismember(string $key, mixed $value): \Redis|bool
            {
                return isset($this->storage[$key]) && in_array($value, (array) $this->storage[$key], true);
            }
            public function sadd(string $key, mixed $value, mixed ...$other_values): \Redis|int|false
            {
                $members = array_merge([$value], $other_values);
                if (!isset($this->storage[$key]))
                    $this->storage[$key] = [];
                $count = 0;
                foreach ($members as $item) {
                    if (is_string($item) && !in_array($item, $this->storage[$key], true)) {
                        $this->storage[$key][] = $item;
                        $count++;
                    }
                }
                return $count;
            }
            public function expire(string $key, int $timeout, ?string $mode = null): \Redis|bool
            {
                return true;
            }
            public function del(string|array $key, string ...$other_keys): \Redis|int|false
            {
                $keys = array_merge(is_array($key) ? $key : [$key], $other_keys);
                foreach ($keys as $k)
                    unset($this->storage[$k]);
                return count($keys);
            }
        };

        $containerBuilder->addDefinitions([
            \Redis::class => $redisFake,
            \App\Infrastructure\Email\EmailProvider::class => $this->createMock(\App\Infrastructure\Email\EmailProvider::class),
            \Psr\Log\LoggerInterface::class => $this->createMock(\Psr\Log\LoggerInterface::class),
            \App\Infrastructure\Metrics\MetricService::class => function () {
                return new \App\Infrastructure\Metrics\MetricService([], new \Prometheus\Storage\InMemory());
            }
        ]);

        $container = $containerBuilder->build();

        // Initialize DB connection once
        $container->get('db');

        // Wire audit observer via container (replaces old static observe in BaseModel::boot)
        try {
            $observer = $container->get(\App\Modules\Audit\AuditObserver::class);
            foreach ([\App\Modules\User\User::class, \App\Modules\Product\Product::class, \App\Modules\Role\Role::class, \App\Modules\Feature\Feature::class] as $modelClass) {
                $modelClass::observe($observer);
            }
        } catch (\Exception $e) {
            error_log("AuditObserver init failed: " . $e->getMessage());
        }

        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $routes = require __DIR__ . '/../config/routes.php';
        $routes($app);

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->add(\App\Middleware\TrailingSlashMiddleware::class);

        $app->add(\App\Middleware\LogMiddleware::class);

        $app->addErrorMiddleware(false, false, false);

        $app->add(\App\Middleware\RateLimitMiddleware::class);

        return $app;
    }

    protected function getTokenForUser(string $userId, array $permissions = []): string
    {
        /** @var \App\Infrastructure\Auth\JwtService $jwtService */
        $jwtService = $this->getContainer()->get(\App\Infrastructure\Auth\JwtService::class);
        $user = \App\Modules\User\User::find($userId);
        
        $tokens = $jwtService->createTokenPair($userId, [
            'email' => $user?->email ?? 'test@example.com',
            'roleId' => $user?->id_role ?? 'user',
            'permissions' => $permissions
        ]);
        
        $jwtService->registerTokens($userId, [$tokens['token']]);
        
        return $tokens['token'];
    }

    /**
     * @param string $method
     * @param string $path
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     * @param array<string, mixed> $serverParams
     * @return ServerRequestInterface
     */
    protected function createRequest(
        string $method,
        string $path,
        array $headers = ['HTTP_ACCEPT' => 'application/json'],
        array $cookies = [],
        array $serverParams = []
    ): ServerRequestInterface {
        $uri = (new \Slim\Psr7\Factory\UriFactory())->createUri($path);
        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest($method, $uri, $serverParams);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request->withCookieParams($cookies);
    }

    /**
     * @param string $method
     * @param string $path
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     * @return ResponseInterface
     */
    protected function request(
        string $method,
        string $path,
        ?array $body = null,
        array $headers = []
    ): ResponseInterface {
        $request = $this->createRequest($method, $path, array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ], $headers));

        if ($body !== null) {
            $json = (string) json_encode($body);
            $request->getBody()->write($json);
            $request->getBody()->rewind();
            $request = $request->withParsedBody($body);
        }

        return $this->app->handle($request);
    }
}
