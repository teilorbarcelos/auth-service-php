<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

class UserSession
{
    private static ?self $instance = null;

    private ?string $userId = null;
    /** @var array<string, mixed>|null */
    private ?array $user = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(?self $session): void
    {
        self::$instance = $session;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /** @param array<string, mixed> $claims */
    public static function resolveRoleId(array $claims): string
    {
        $role = $claims['role'] ?? null;
        $roleId = $claims['id_role'] ?? $claims['roleId'] ?? null;
        if ($roleId === null && is_array($role)) {
            $roleId = $role['id'] ?? null;
        }
        if ($roleId === null) {
            $roleId = $role;
        }
        return is_scalar($roleId) ? (string) $roleId : '';
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    /** @param array<string, mixed>|null $user */
    public function setUser(?array $user): void
    {
        if ($user === null) {
            $this->user = null;
            $this->userId = null;
            return;
        }

        $rawId = $user['uid'] ?? $user['id'] ?? null;
        if (is_scalar($rawId)) {
            $this->userId = (string) $rawId;
        }

        $roleId = self::resolveRoleId($user);
        if ($roleId !== '') {
            $user['id_role'] = $roleId;
        }

        $this->user = $user;
    }

    /** @return array<string, mixed>|null */
    public function getUser(): ?array
    {
        return $this->user;
    }

    public function hasPermission(string $feature, string $action): bool
    {
        if (!$this->user || !isset($this->user['permissions'])) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        /** @var array<int, array<string, mixed>> $permissions */
        $permissions = $this->user['permissions'];
        $hasPermission = false;

        foreach ($permissions as $permission) {
            if (isset($permission['feature']) && $permission['feature'] === $feature) {
                $hasPermission = (bool) ($permission[$action] ?? false);
                break;
            }
        }

        return $hasPermission;
    }

    public function isAdmin(): bool
    {
        $roleId = $this->user['id_role'] ?? '';
        $roleIdStr = is_scalar($roleId) ? (string) $roleId : '';
        return in_array($roleIdStr, ['admin', 'administrator'], true);
    }
}
