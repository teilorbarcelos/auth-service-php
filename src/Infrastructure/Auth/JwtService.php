<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use DateTimeImmutable;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class JwtService
{
    private Configuration $config;

    public static ?string $forcedSecret = null;

    public static bool $forceFallback = false;

    private const CACHE_TTL = 5;
    private const SV_CACHE_TTL = 5;
    private const CACHE_MAX = 500;
    /** @var array<string, array<string, array{0: bool, 1: int}>> */
    private array $sessionCache = [];
    /** @var array<string, array{0: int, 1: int}> */
    private array $svCache = [];

    public function __construct(
        private readonly \Redis $redis,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
        $secret = ($_ENV['JWT_SECRET'] ?? '');

        if (($_ENV['APP_ENV'] ?? '') === 'production' && (!$secret || $secret === 'default-secret-key-at-least-32-chars-long')) {
            throw new \LogicException('Segurança Crítica: JWT_SECRET não configurado adequadamente para produção.');
        }

        if (!$secret || self::$forceFallback) {
            $secret = 'default-secret-key-at-least-32-chars-long';
        }

        $this->config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($secret)
        );
    }

    public function getSessionVersion(string $userId): int
    {
        $now = time();
        if (isset($this->svCache[$userId]) && $this->svCache[$userId][1] > $now) {
            return $this->svCache[$userId][0];
        }

        $key = "user:session_version:{$userId}";
        $raw = $this->redis->get($key);

        if ($raw === false || $raw === null) {
            $this->redis->setnx($key, 1);
            $version = 1;
        } elseif (is_numeric($raw)) {
            $version = (int) $raw;
        } else {
            $version = 0;
        }

        if (count($this->svCache) >= self::CACHE_MAX) {
            array_shift($this->svCache);
        }

        $this->svCache[$userId] = [$version, $now + self::SV_CACHE_TTL];
        return $version;
    }

    public function bumpSessionVersion(string $userId): void
    {
        $this->redis->incr("user:session_version:{$userId}");
        unset($this->svCache[$userId]);
    }

    public function removeToken(string $userId, string $token): void
    {
        $this->redis->srem("user:sessions:{$userId}", $token);
        if (isset($this->sessionCache[$userId])) {
            $tokenKey = hash('sha256', $token);
            unset($this->sessionCache[$userId][$tokenKey]);
        }
    }

    /**
     * @param string $userId
     * @param string[] $tokens
     * @return void
     */
    public function registerTokens(string $userId, array $tokens): void
    {
        $key = "user:sessions:{$userId}";
        $ttl = 7 * 24 * 3600;

        $this->redis->sadd($key, ...$tokens);
        $this->redis->expire($key, $ttl);
    }

    /**
     * @param string $userId
     * @param string $token
     * @return bool
     */
    public function isTokenValid(string $userId, string $token): bool
    {
        $now = time();

        if (isset($this->sessionCache[$userId])) {
            $tokenKey = hash('sha256', $token);
            if (isset($this->sessionCache[$userId][$tokenKey]) && $this->sessionCache[$userId][$tokenKey][1] > $now) {
                return $this->sessionCache[$userId][$tokenKey][0];
            }
        }

        $redisKey = "user:sessions:{$userId}";
        $result = (bool) $this->redis->sismember($redisKey, $token);

        if (count($this->sessionCache) >= self::CACHE_MAX) {
            array_shift($this->sessionCache);
        }

        $tokenKey = hash('sha256', $token);
        $this->sessionCache[$userId][$tokenKey] = [$result, $now + self::CACHE_TTL];

        return $result;
    }

    /**
     * @param string $userId
     * @return void
     */
    public function invalidateUserTokens(string $userId): void
    {
        $redisKey = "user:sessions:{$userId}";
        $this->redis->del([$redisKey]);
        $this->bumpSessionVersion($userId);

        unset($this->sessionCache[$userId]);
    }

    /**
     * @param string $userId
     * @param array<string, mixed> $claims
     * @param string $expiresIn
     * @return string
     */
    public function createToken(string $userId, array $claims = [], string $expiresIn = '3600 seconds'): string
    {
        $now = new DateTimeImmutable();
        $sv = $this->getSessionVersion($userId);
        $builder = $this->config->builder()
            ->issuedBy($_ENV['APP_URL'] ?? 'http://localhost')
            ->permittedFor($_ENV['APP_URL'] ?? 'http://localhost')
            ->issuedAt($now)
            ->expiresAt($now->modify('+' . $expiresIn))
            ->withClaim('uid', $userId)
            ->withClaim('sv', $sv);

        foreach ($claims as $key => $value) {
            /** @var string $key */
            $builder = $builder->withClaim($key, $value);
        }

        return $builder->getToken($this->config->signer(), $this->config->signingKey())->toString();
    }

    /**
     * @param string $userId
     * @param array<string, mixed> $claims
     * @return array{token: string, refreshToken: string}
     */
    public function createTokenPair(string $userId, array $claims = []): array
    {
        $token = $this->createToken($userId, $claims, ($_ENV['JWT_EXPIRATION'] ?? '3600') . ' seconds');
        $refreshToken = $this->createToken($userId, $claims, '7 days');

        return [
            'token' => $token,
            'refreshToken' => $refreshToken
        ];
    }

    public static bool $forceInvalidToken = false;

    /**
     * @param string $token
     * @return array<string, mixed>|null
     */
    public function validateToken(string $token): ?array
    {
        try {
            if (empty($token) || self::$forceInvalidToken)
                return null;

            /** @var UnencryptedToken $jwtToken */
            $jwtToken = $this->config->parser()->parse($token);

            $constraints = $this->config->validationConstraints();

            $constraints[] = new LooseValidAt(
                new SystemClock(new DateTimeZone('UTC'))
            );

            if (!$this->config->validator()->validate($jwtToken, ...$constraints)) {
                return null;
            }

            $claims = $jwtToken->claims()->all();

            /** @var string|null $uid */
            $uid = $claims['uid'] ?? null;
            /** @var int|null $tokenSv */
            $tokenSv = $claims['sv'] ?? null;
            if ($uid !== null && $tokenSv !== null) {
                $currentSv = $this->getSessionVersion($uid);
                if ($tokenSv !== $currentSv) {
                    return null;
                }
            }

            return $claims;
        } catch (\Exception $e) {
            $this->logger->warning('JWT validation failed: ' . $e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }
}
