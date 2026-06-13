<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\User\User;
use App\Modules\User\UserAuth;
use App\Infrastructure\Auth\JwtService;
use App\Infrastructure\Email\EmailProvider;
use App\Infrastructure\Email\EmailTemplates;
use App\Core\Traits\ValidatableTrait;
use Respect\Validation\Validator as v;

class AuthService
{
    use ValidatableTrait;
    const USER_NOT_FOUND = 'User not found';
    public function __construct(
        private JwtService $jwtService,
        private EmailProvider $emailProvider,
        private readonly ?\Redis $redis = null,
    ) {
    }

    /**
     * @param string $email
     * @param string $password
     * @return array<string, mixed>
     */
    public function login(string $email, string $password): array
    {
        $this->validate(['email' => $email, 'password' => $password], [
            'email' => v::email()->notEmpty(),
            'password' => v::stringType()->notEmpty(),
        ]);

        $user = User::with(['role', 'role.features'])->where('email', $email)->first();

        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Invalid credentials', 401);
        }

        if (!$user->auth instanceof \App\Modules\User\UserAuth || !password_verify($password, (string) $user->auth->password)) {
            throw new \InvalidArgumentException('Invalid credentials', 401);
        }

        if (!$user->active) {
            throw new \DomainException('Account is disabled', 403);
        }

        if ($user->role instanceof \App\Modules\Role\Role && !$user->role->active) {
            throw new \DomainException('Role is disabled', 403);
        }

        return $this->createAuthResponse($user, 'Login successful');
    }

    /**
     * @param string $userId
     * @return array<string, mixed>
     */
    public function getMe(string $userId): array
    {
        $user = User::with(['role', 'role.features'])->find($userId);
        if (!$user instanceof User) {
            throw new \DomainException(self::USER_NOT_FOUND, 404);
        }

        return $this->createAuthResponse($user, 'User found');
    }

    /**
     * @param string $refreshToken
     * @return array<string, mixed>
     */
    public function refreshToken(string $refreshToken): array
    {
        $claims = $this->jwtService->validateToken($refreshToken);
        $uid = $claims['uid'] ?? null;

        if (!$claims || !$uid || (!is_string($uid) && !is_numeric($uid))) {
            throw new \InvalidArgumentException('Invalid or expired refresh token', 401);
        }

        $uid = (string) $uid;

        if (!$this->jwtService->isTokenValid($uid, $refreshToken)) {
            throw new \InvalidArgumentException('Invalid or expired refresh token', 401);
        }

        $this->jwtService->removeToken($uid, $refreshToken);

        return $this->getMe($uid);
    }

    /**
     * @param string $email
     * @return void
     */
    public function requestPasswordReset(string $email): void
    {
        $user = User::where('email', $email)->first();
        if (!$user instanceof User || !$user->auth instanceof \App\Modules\User\UserAuth) {
            return;
        }

        $token = (string) random_int(100000, 999999);
        $expiration = new \DateTime('+15 minutes');

        $user->auth->update([
            'request_password_token' => $token,
            'request_password_expiration' => $expiration->format('Y-m-d H:i:s')
        ]);

        $html = EmailTemplates::render(EmailTemplates::FORGOT_PASSWORD_TEMPLATE, [
            'name' => $user->name,
            'token' => $token
        ]);

        $this->emailProvider->sendEmail($email, 'Recuperação de Senha', $html);
    }

    /**
     * @param string $email
     * @param string $token
     * @return bool
     */
    public function validateResetToken(string $email, string $token): bool
    {
        $user = User::where('email', $email)->first();
        if (!$user instanceof User || !$user->auth instanceof \App\Modules\User\UserAuth) {
            throw new \DomainException(self::USER_NOT_FOUND, 404);
        }

        if ($user->auth->request_password_token !== $token) {
            throw new \InvalidArgumentException('Invalid reset token', 401);
        }

        $expiration = $user->auth->request_password_expiration;
        if ($expiration && new \DateTime($expiration) < new \DateTime()) {
            throw new \DomainException('Reset token has expired', 401);
        }

        return true;
    }

    /**
     * @param string $email
     * @param string $token
     * @param string $newPassword
     * @return void
     */
    public function resetPassword(string $email, string $token, string $newPassword): void
    {
        $this->validateResetToken($email, $token);

        $user = User::where('email', $email)->first();
        // @codeCoverageIgnoreStart
        if (!$user instanceof User || !$user->auth instanceof UserAuth) {
            throw new \DomainException(self::USER_NOT_FOUND, 404);
        }
        // @codeCoverageIgnoreEnd

        $user->auth->update([
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'request_password_token' => null,
            'request_password_expiration' => null,
            'retries' => 0
        ]);

        $this->jwtService->bumpSessionVersion($user->id);
    }

    /**
     * @param User $user
     * @return array<int, array<string, mixed>>
     */
    private function getFormattedPermissions(User $user): array
    {
        if (!$user->role instanceof \App\Modules\Role\Role)
            return [];

        $roleId = $user->role->id;
        $cacheKey = "role:features:{$roleId}";

        if ($this->redis) {
            $cached = $this->redis->get($cacheKey);
            if ($cached !== false && is_string($cached)) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $result = [];
        foreach ($user->role->features as $feature) {
            /** @var \App\Modules\Feature\Feature $feature */
            $perms = $feature->pivot->permissions;

            if (is_string($perms)) {
                $perms = json_decode($perms, true);
            }

            if (!is_array($perms))
                $perms = [];

            $result[] = [
                'feature' => $feature->id,
                'create' => $perms['create'] ?? false,
                'view' => $perms['view'] ?? false,
                'delete' => $perms['delete'] ?? false,
                'activate' => $perms['activate'] ?? false
            ];
        }

        if ($this->redis) {
            $this->redis->setex($cacheKey, 120, json_encode($result));
        }

        return $result;
    }

    /**
     * @param User $user
     * @param string $message
     * @return array<string, mixed>
     */
    private function createAuthResponse(User $user, string $message): array
    {
        $permissions = $this->getFormattedPermissions($user);

        $tokens = $this->jwtService->createTokenPair($user->id, [
            'email' => $user->email,
            'roleId' => $user->id_role,
            'permissions' => $permissions
        ]);

        $this->jwtService->registerTokens($user->id, [$tokens['token'], $tokens['refreshToken']]);

        return [
            'message' => $message,
            'valid' => true,
            'token' => $tokens['token'],
            'refreshToken' => $tokens['refreshToken'],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => [
                    'id' => $user->role->id ?? $user->id_role,
                    'name' => $user->role->name ?? 'User',
                    'description' => $user->role->description ?? '',
                    'permissions' => $permissions
                ]
            ]
        ];
    }
}
