<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Messaging;

use App\Infrastructure\Messaging\RabbitMQProvider;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use ReflectionClass;

class RabbitMQProviderTest extends TestCase
{
    private $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'false';
        parent::tearDown();
    }

    public function testConnectDisabled(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'false';
        $provider = new RabbitMQProvider($this->logger);
        $provider->connect();

        $reflection = new ReflectionClass($provider);
        $property = $reflection->getProperty('connection');
        $property->setAccessible(true);

        $this->assertNull($property->getValue($provider));
    }

    public function testConnectEnabled(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'true';

        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockConn = $this->createMock(AMQPStreamConnection::class);

        // isConnected() is only called when $this->connection is NOT null.
        // During the first connect(), $this->connection is null, so isConnected() is skipped.
        // During the second connect(), $this->connection is set, so isConnected() is called once.
        $mockConn->expects($this->once())->method('isConnected')->willReturn(true);
        $mockConn->expects($this->once())->method('channel')->willReturn($mockChannel);

        $provider = new RabbitMQProvider($this->logger, function () use ($mockConn) {
            return $mockConn;
        });

        $provider->connect(); // Should connect
        $provider->connect(); // Should return early because isConnected is true

        $reflection = new ReflectionClass($provider);
        $property = $reflection->getProperty('connection');
        $property->setAccessible(true);

        $this->assertSame($mockConn, $property->getValue($provider));
    }

    public function testPublishEnabled(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'true';

        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockConn = $this->createMock(AMQPStreamConnection::class);
        $mockConn->method('channel')->willReturn($mockChannel);
        $mockConn->method('isConnected')->willReturn(true);

        $provider = new RabbitMQProvider($this->logger, function () use ($mockConn) {
            return $mockConn;
        });

        // Force connection
        $reflection = new ReflectionClass($provider);
        $prop = $reflection->getProperty('connection');
        $prop->setAccessible(true);
        $prop->setValue($provider, $mockConn);
        $chanProp = $reflection->getProperty('channel');
        $chanProp->setAccessible(true);
        $chanProp->setValue($provider, $mockChannel);

        $mockChannel->expects($this->once())->method('queue_declare');
        $mockChannel->expects($this->once())->method('basic_publish');

        $provider->publish('test_queue', ['msg' => 'hello']);
    }

    public function testPublishDisabled(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'false';
        $provider = new RabbitMQProvider($this->logger);

        // Should return early
        $provider->publish('test_queue', ['msg' => 'hello']);
        $this->assertTrue(true);
    }

    public function testSubscribeEnabled(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'true';

        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockConn = $this->createMock(AMQPStreamConnection::class);
        $mockConn->method('channel')->willReturn($mockChannel);
        $mockConn->method('isConnected')->willReturn(true);

        $provider = new RabbitMQProvider($this->logger, function () use ($mockConn) {
            return $mockConn;
        });

        // Force connection
        $reflection = new ReflectionClass($provider);
        $prop = $reflection->getProperty('connection');
        $prop->setAccessible(true);
        $prop->setValue($provider, $mockConn);
        $chanProp = $reflection->getProperty('channel');
        $chanProp->setAccessible(true);
        $chanProp->setValue($provider, $mockChannel);

        $mockChannel->expects($this->once())->method('queue_declare');
        $mockChannel->expects($this->once())->method('basic_consume');
        $mockChannel->expects($this->exactly(2))->method('is_consuming')->willReturnOnConsecutiveCalls(true, false);
        $mockChannel->expects($this->once())->method('wait');

        $provider->subscribe('test_queue', function ($msg) {});
    }

    public function testSubscribeDisabled(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'false';
        $provider = new RabbitMQProvider($this->logger);

        // Should return early
        $provider->subscribe('test_queue', function ($msg) {});
        $this->assertTrue(true);
    }

    public function testConnectFailureRetries(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'true';

        $factory = $this->getMockBuilder(\stdClass::class)->addMethods(['__invoke'])->getMock();
        $factory->expects($this->exactly(3))
            ->method('__invoke')
            ->willThrowException(new \Exception("Connection failed"));

        $provider = new RabbitMQProvider($this->logger, $factory);

        $this->logger->expects($this->exactly(3))->method('warning');
        $this->logger->expects($this->once())->method('error');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Connection failed");

        $provider->connect();
    }

    public function testDisconnect(): void
    {
        $mockConn = $this->createMock(AMQPStreamConnection::class);
        $mockChannel = $this->createMock(AMQPChannel::class);

        $provider = new RabbitMQProvider($this->logger, function () use ($mockConn) {
            return $mockConn;
        });

        $reflection = new ReflectionClass($provider);

        $connProp = $reflection->getProperty('connection');
        $connProp->setAccessible(true);
        $connProp->setValue($provider, $mockConn);

        $chanProp = $reflection->getProperty('channel');
        $chanProp->setAccessible(true);
        $chanProp->setValue($provider, $mockChannel);

        $mockChannel->expects($this->once())->method('close');
        $mockConn->expects($this->once())->method('close');

        $provider->disconnect();

        // Test disconnect when null
        $connProp->setValue($provider, null);
        $chanProp->setValue($provider, null);
        $provider->disconnect(); // Should not throw
    }
    public function testDefaultConnectionFactory(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'true';
        $_ENV['RABBIT_HOST'] = 'localhost';
        $provider = new RabbitMQProvider($this->logger);

        try {
            $provider->connect();
        } catch (\Exception $e) {
            // Expected to fail as no RabbitMQ is running on localhost
        }
        $this->assertTrue(true);
    }

    public function testPublishLazyConnect(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'true';
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockConn = $this->createMock(AMQPStreamConnection::class);
        $mockConn->method('channel')->willReturn($mockChannel);
        $mockConn->method('isConnected')->willReturn(true);

        $provider = new RabbitMQProvider($this->logger, function () use ($mockConn) {
            return $mockConn;
        });

        $mockChannel->expects($this->once())->method('queue_declare');
        $mockChannel->expects($this->once())->method('basic_publish');

        $provider->publish('test_queue', ['msg' => 'hello']);
    }

    public function testSubscribeLazyConnect(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'true';
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockConn = $this->createMock(AMQPStreamConnection::class);
        $mockConn->method('channel')->willReturn($mockChannel);
        $mockConn->method('isConnected')->willReturn(true);

        $provider = new RabbitMQProvider($this->logger, function () use ($mockConn) {
            return $mockConn;
        });

        $mockChannel->expects($this->once())->method('queue_declare');
        $mockChannel->expects($this->once())->method('basic_consume');
        $mockChannel->method('is_consuming')->willReturn(false);

        $provider->subscribe('test_queue', function ($msg) {});
    }

    public function testSubscribeCallbackExecution(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'true';
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockConn = $this->createMock(AMQPStreamConnection::class);
        $mockConn->method('channel')->willReturn($mockChannel);

        $provider = new RabbitMQProvider($this->logger, function () use ($mockConn) {
            return $mockConn;
        });

        $reflection = new ReflectionClass($provider);
        $chanProp = $reflection->getProperty('channel');
        $chanProp->setAccessible(true);
        $chanProp->setValue($provider, $mockChannel);

        $callbackCaptured = null;
        $mockChannel->method('basic_consume')->willReturnCallback(function ($q, $t, $nl, $na, $e, $nw, $callback) use (&$callbackCaptured) {
            $callbackCaptured = $callback;
            return null;
        });
        $mockChannel->method('is_consuming')->willReturn(false);

        $executed = false;
        $capturedContent = null;
        $provider->subscribe('test_queue', function ($content) use (&$executed, &$capturedContent) {
            $executed = true;
            $capturedContent = $content;
        });

        $msg = new AMQPMessage(json_encode(['foo' => 'bar']));
        $callbackCaptured($msg);

        $this->assertTrue($executed);
        $this->assertEquals(['foo' => 'bar'], $capturedContent);
    }

    public function testSubscribeCallbackError(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'true';
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockConn = $this->createMock(AMQPStreamConnection::class);
        $mockConn->method('channel')->willReturn($mockChannel);

        $provider = new RabbitMQProvider($this->logger, function () use ($mockConn) {
            return $mockConn;
        });

        $reflection = new ReflectionClass($provider);
        $chanProp = $reflection->getProperty('channel');
        $chanProp->setAccessible(true);
        $chanProp->setValue($provider, $mockChannel);

        $callbackCaptured = null;
        $mockChannel->method('basic_consume')->willReturnCallback(function ($q, $t, $nl, $na, $e, $nw, $callback) use (&$callbackCaptured) {
            $callbackCaptured = $callback;
            return null;
        });
        $mockChannel->method('is_consuming')->willReturn(false);

        $provider->subscribe('q', function () {
            throw new \Exception("Callback error"); });

        $this->logger->expects($this->once())->method('error')->with($this->stringContains("Callback error"));

        $msg = new AMQPMessage(json_encode(['data' => 'test']));
        $callbackCaptured($msg);
    }
    public function testPublishJsonEncodeFailure(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'true';
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockConn = $this->createMock(AMQPStreamConnection::class);
        $mockConn->method('channel')->willReturn($mockChannel);
        $mockConn->method('isConnected')->willReturn(true);

        $provider = new RabbitMQProvider($this->logger, function () use ($mockConn) {
            return $mockConn;
        });

        // Set channel via reflection
        $reflection = new ReflectionClass($provider);
        $chanProp = $reflection->getProperty('channel');
        $chanProp->setAccessible(true);
        $chanProp->setValue($provider, $mockChannel);

        $mockChannel->expects($this->once())->method('basic_publish')->with(
            $this->callback(function ($msg) {
                return $msg->body === '';
            })
        );

        // Resources cannot be json_encoded
        $provider->publish('q', ['res' => fopen('php://temp', 'r')]);
    }

    public function testPublishChannelNullAfterConnect(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'true';
        $mockConn = $this->createMock(AMQPStreamConnection::class);
        $mockConn->method('isConnected')->willReturn(true);

        $provider = new RabbitMQProvider($this->logger, function () use ($mockConn) {
            return $mockConn;
        });

        // Force connection state without channel
        $reflection = new ReflectionClass($provider);
        $connProp = $reflection->getProperty('connection');
        $connProp->setAccessible(true);
        $connProp->setValue($provider, $mockConn);
        
        $chanProp = $reflection->getProperty('channel');
        $chanProp->setAccessible(true);
        $chanProp->setValue($provider, null);

        $provider->publish('q', ['msg' => 'hi']);
        $this->assertNull($chanProp->getValue($provider));
    }

    public function testSubscribeChannelNullAfterConnect(): void
    {
        $_ENV['MESSAGING_ENABLED'] = 'true';
        $mockConn = $this->createMock(AMQPStreamConnection::class);
        $mockConn->method('isConnected')->willReturn(true);

        $provider = new RabbitMQProvider($this->logger, function () use ($mockConn) {
            return $mockConn;
        });

        // Force connection state without channel
        $reflection = new ReflectionClass($provider);
        $connProp = $reflection->getProperty('connection');
        $connProp->setAccessible(true);
        $connProp->setValue($provider, $mockConn);
        
        $chanProp = $reflection->getProperty('channel');
        $chanProp->setAccessible(true);
        $chanProp->setValue($provider, null);

        $provider->subscribe('q', function () {});
        $this->assertNull($chanProp->getValue($provider));
    }
}


