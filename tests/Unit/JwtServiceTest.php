<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Auth\JwtService;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use PHPUnit\Framework\TestCase;

class JwtServiceTest extends TestCase
{
    private string $originalEnv;
    private string $originalSecret;

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV['APP_ENV'] ?? '';
        $this->originalSecret = $_ENV['JWT_SECRET'] ?? '';
    }

    protected function tearDown(): void
    {
        $_ENV['APP_ENV'] = $this->originalEnv;
        $_ENV['JWT_SECRET'] = $this->originalSecret;
    }

    public function test_should_throw_exception_in_production_with_default_secret(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_ENV['JWT_SECRET'] = 'default-secret-key-at-least-32-chars-long';

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Segurança Crítica: JWT_SECRET não configurado adequadamente para produção.');

        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        new JwtService($redis);
    }

    public function test_should_work_in_production_with_custom_secret(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_ENV['JWT_SECRET'] = 'my-super-secret-key-12345';

        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        $service = new JwtService($redis);
        $this->assertInstanceOf(JwtService::class, $service);
    }

    public function test_should_use_fallback_secret_in_dev_when_missing(): void
    {
        $_ENV['APP_ENV'] = 'dev';
        $_ENV['JWT_SECRET'] = '';

        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        $service = new JwtService($redis);
        $this->assertInstanceOf(JwtService::class, $service);
    }

    public function test_should_return_null_on_malformed_token(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        $service = new JwtService($redis);
        $result = $service->validateToken('invalid.token.here');
        $this->assertNull($result);
    }

    public function test_register_tokens(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->expects($this->once())->method('sadd')->with('user:sessions:123', 't1', 't2');
        $redis->expects($this->once())->method('expire');

        $service = new JwtService($redis);
        $service->registerTokens('123', ['t1', 't2']);
    }

    public function test_is_token_valid(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->method('sismember')->willReturn(true);

        $service = new JwtService($redis);
        $this->assertTrue($service->isTokenValid('123', 'token'));
    }

    public function test_is_token_valid_cache_hit(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->expects($this->once())->method('sismember')->willReturn(true);

        $service = new JwtService($redis);

        $this->assertTrue($service->isTokenValid('123', 'token'));
        $this->assertTrue($service->isTokenValid('123', 'token'));
    }

    public function test_is_token_valid_cache_eviction(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->method('sismember')->willReturn(true);

        $service = new JwtService($redis);

        for ($i = 0; $i < 505; $i++) {
            $service->isTokenValid("user{$i}", "token{$i}");
        }

        $this->assertTrue($service->isTokenValid('user0', 'token0'));
    }

    public function test_invalidate_user_tokens(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->expects($this->once())->method('del')->with(['user:sessions:123']);
        $redis->expects($this->once())->method('incr')->with('user:session_version:123');

        $service = new JwtService($redis);
        $service->invalidateUserTokens('123');
    }

    public function test_create_token_pair(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->method('get')->willReturn('1');

        $service = new JwtService($redis);
        $pair = $service->createTokenPair('123', ['email' => 'test@test.com']);

        $this->assertArrayHasKey('token', $pair);
        $this->assertArrayHasKey('refreshToken', $pair);
    }

    public function test_validate_token_expired(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->method('get')->willReturn('1');

        $service = new JwtService($redis);
        $token = $service->createToken('123', [], '-1 second');

        $this->assertNull($service->validateToken($token));
    }

    public function test_get_session_version_creates_key_when_missing(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->expects($this->once())->method('get')->with('user:session_version:123')->willReturn(null);
        $redis->expects($this->once())->method('setnx')->with('user:session_version:123', 1);

        $service = new JwtService($redis);
        $version = $service->getSessionVersion('123');
        $this->assertEquals(1, $version);
    }

    public function test_get_session_version_returns_cached_value(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->expects($this->once())->method('get')->with('user:session_version:123')->willReturn('5');

        $service = new JwtService($redis);
        $this->assertEquals(5, $service->getSessionVersion('123'));
        $this->assertEquals(5, $service->getSessionVersion('123'));
    }

    public function test_bump_session_version_increments_and_clears_cache(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->method('get')->willReturn('1');
        $redis->expects($this->once())->method('incr')->with('user:session_version:123');

        $service = new JwtService($redis);
        $service->bumpSessionVersion('123');
    }

    public function test_remove_token_removes_from_set_and_cache(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->method('sismember')->willReturn(true);
        $redis->expects($this->once())->method('srem')->with('user:sessions:123', 'some-token');

        $service = new JwtService($redis);
        $service->isTokenValid('123', 'some-token');
        $service->removeToken('123', 'some-token');
    }

    public function test_validate_token_rejects_wrong_session_version(): void
    {
        $redis1 = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();
        $redis1->method('get')->willReturn('1');
        $service1 = new JwtService($redis1);
        $token = $service1->createToken('123', []);
        $this->assertNotNull($service1->validateToken($token));

        $redis2 = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();
        $redis2->method('get')->willReturn('2');
        $service2 = new JwtService($redis2);
        $this->assertNull($service2->validateToken($token));
    }

    public function test_validate_token_backward_compat_without_sv(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();

        $service = new JwtService($redis);

        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText('test-secret-key-that-is-long-enough-for-hs256')
        );

        $now = new \DateTimeImmutable();
        $token = $config->builder()
            ->issuedBy('http://localhost')
            ->permittedFor('http://localhost')
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('uid', '123')
            ->getToken($config->signer(), $config->signingKey())
            ->toString();

        $this->assertNotNull($service->validateToken($token));
    }

    public function test_force_invalid_token_returns_null(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        $service = new JwtService($redis);
        JwtService::$forceInvalidToken = true;
        $this->assertNull($service->validateToken('anything'));
        JwtService::$forceInvalidToken = false;
    }

    public function test_sv_cache_eviction(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->method('get')->willReturn('1');

        $service = new JwtService($redis);

        for ($i = 0; $i < 505; $i++) {
            $service->getSessionVersion("user{$i}");
        }

        $this->assertEquals(1, $service->getSessionVersion('user0'));
    }

    public function test_get_session_version_non_numeric(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
                        ->getMock();

        $redis->method('get')->willReturn('not-numeric');

        $service = new JwtService($redis);
        $version = $service->getSessionVersion('123');
        $this->assertEquals(0, $version);
    }
}
