<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class RabbitMQProvider
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;
    private bool $enabled;
    /** @var array<string, mixed> */
    private array $config;
    /** @var (callable(): AMQPStreamConnection) */
    private $connectionFactory;

    public function __construct(private LoggerInterface $logger, ?callable $connectionFactory = null)
    {
        $this->enabled = ($_ENV['MESSAGING_ENABLED'] ?? 'false') === 'true';
        $this->config = [
            'host' => $_ENV['RABBIT_HOST'] ?? 'rabbitmq',
            'port' => (int)($_ENV['RABBIT_PORT'] ?? 5672),
            'user' => $_ENV['RABBIT_USER'] ?? 'guest',
            'password' => $_ENV['RABBIT_PASSWORD'] ?? 'guest',
        ];
        $this->connectionFactory = $connectionFactory ?: function() {
            /** @var string $host */
            $host = $this->config['host'];
            /** @var int $port */
            $port = $this->config['port'];
            /** @var string $user */
            $user = $this->config['user'];
            /** @var string $password */
            $password = $this->config['password'];

            return new AMQPStreamConnection($host, $port, $user, $password, '/', false, 'AMQPLAIN', null, 'en_US', 3.0, 10.0, null, false, 30);
        };
    }

    public function connect(): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($this->connection && $this->connection->isConnected()) {
            return;
        }

        $retries = 3;
        $delay = 1;
        $lastException = null;

        for ($i = 0; $i < $retries; $i++) {
            try {
                $this->connection = ($this->connectionFactory)();
                $this->channel = $this->connection->channel();
                $this->logger->info("[RabbitMQ] Connected successfully");
                return;
            } catch (\Exception $e) {
                $lastException = $e;
                $this->logger->warning(sprintf(
                    "[RabbitMQ] Connection attempt %d failed: %s. Retrying in %ds...",
                    $i + 1,
                    $e->getMessage(),
                    $delay
                ));
                if ($i < $retries - 1) {
                    sleep($delay);
                    $delay *= 2;
                }
            }
        }

        $this->logger->error("[RabbitMQ] All connection attempts failed after retries.");
        throw $lastException;
    }

    /** @param array<mixed> $message */
    public function publish(string $queueName, array $message): void
    {
        if (!$this->enabled) {
            return;
        }

        if (!$this->channel) {
            $this->connect();
        }

        $channel = $this->channel;
        if (!$channel) {
            return;
        }

        $channel->queue_declare($queueName, false, true, false, false);

        $msgBody = json_encode($message);
        if ($msgBody === false) {
            $msgBody = '';
        }

        $msg = new AMQPMessage($msgBody, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        $channel->basic_publish($msg, '', $queueName);
    }

    public function subscribe(string $queueName, callable $callback): void
    {
        if (!$this->enabled) {
            return;
        }

        if (!$this->channel) {
            $this->connect();
        }

        $channel = $this->channel;
        if (!$channel) {
            return;
        }

        $channel->queue_declare($queueName, false, true, false, false);

        $channel->basic_consume($queueName, '', false, true, false, false, function (AMQPMessage $msg) use ($callback) {
            try {
                $content = json_decode($msg->body, true);
                $callback($content);
            } catch (\Exception $e) {
                $this->logger->error(sprintf("[RabbitMQ] Error processing message: %s", $e->getMessage()));
            }
        });

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    public function disconnect(): void
    {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
        $this->logger->info("[RabbitMQ] Disconnected");
    }
}
