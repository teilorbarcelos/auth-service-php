<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;

class DatabaseProvider
{
    private static ?self $instance = null;
    private Capsule $capsule;
    private Dispatcher $dispatcher;

    private function __construct()
    {
        $this->capsule = new Capsule();
        $this->capsule->addConnection([
            'driver' => $_ENV['DB_DRIVER'] ?? 'pgsql',
            'host' => $_ENV['DB_HOST'] ?? 'db',
            'database' => $_ENV['DB_DATABASE'] ?? 'backend_php_slim_db',
            'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
            'password' => $_ENV['DB_PASSWORD'] ?? 'postgrespw',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8',
            'prefix' => '',
            'options' => [
                \PDO::ATTR_PERSISTENT => true, // Dev OK. Prod worker mode: usar PgBouncer ou remover.
            ],
        ]);

        $this->dispatcher = new Dispatcher(new IlluminateContainer());
        $this->capsule->setEventDispatcher($this->dispatcher);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function getCapsule(): Capsule
    {
        return $this->capsule;
    }

    public function getDispatcher(): Dispatcher
    {
        return $this->dispatcher;
    }

    public function listenQueryExecuted(callable $callback): void
    {
        $this->dispatcher->listen(
            \Illuminate\Database\Events\QueryExecuted::class,
            $callback
        );
    }
}
