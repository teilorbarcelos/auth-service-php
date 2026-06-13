<?php

declare(strict_types=1);

namespace App\Modules\Role;

use App\Core\BaseService;
use App\Modules\Role\Role;
use App\Modules\Role\RoleRepository;
use Illuminate\Database\Eloquent\Collection;

use Illuminate\Database\Eloquent\Model;
use Respect\Validation\Validator as v;

/**
 * @extends BaseService<Role, RoleRepository>
 */
class RoleService extends BaseService
{
    public function __construct(
        RoleRepository $repository,
        private readonly \App\Infrastructure\Auth\JwtService $jwtService,
        private readonly ?\Redis $redis = null,
    ) {
        $this->repository = $repository;
        $this->filterableFields = ['name', 'active'];
        $this->searchableFields = ['name'];
    }

    public function listFeatures(): Collection
    {
        return \App\Modules\Feature\Feature::all();
    }

    /** @return Collection<int, Role> */
    public function listAll(): Collection
    {
        return $this->repository->all();
    }

    /**
     * @param array<string, mixed> $data
     * @return Role
     */
    public function create(array $data): Model
    {
        $this->validate($data, [
            'id' => v::optional(v::stringType()->notEmpty()->length(2, 50)),
            'name' => v::stringType()->notEmpty()->length(3, 255),
            'description' => v::optional(v::stringType()),
            'permissions' => v::optional(v::arrayType()),
        ]);

        /** @var Role $role */
        $role = $this->repository->create($data);

        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $this->syncFeatures($role, $data['permissions']);
        }

        return $role;
    }

    /**
     * @param string $id
     * @param array<string, mixed> $data
     * @return Role|null
     */
    public function update(string $id, array $data): ?Model
    {
        $this->validate($data, [
            'name' => v::optional(v::stringType()->notEmpty()->length(3, 255)),
            'description' => v::optional(v::stringType()),
            'permissions' => v::optional(v::arrayType()),
            'active' => v::optional(v::boolType()),
        ]);

        /** @var Role|null $role */
        $role = $this->repository->update($id, $data);

        if ($role && isset($data['permissions']) && is_array($data['permissions'])) {
            $this->syncFeatures($role, $data['permissions']);
        }

        if ($role && (isset($data['permissions']) || isset($data['active']))) {
            $this->clearRoleCache($id);
            $this->invalidateUsersWithRole($id);
        }

        return $role;
    }

    public function setStatus(string $id, bool $active): ?Model
    {
        $role = parent::setStatus($id, $active);
        if ($role instanceof Model) {
            $this->clearRoleCache($id);
            $this->invalidateUsersWithRole($id);
            return $role;
        }
        return null;
    }

    private function clearRoleCache(string $roleId): void
    {
        $this->redis?->del("role:features:{$roleId}");
    }

    private function invalidateUsersWithRole(string $roleId): void
    {
        $userIds = \Illuminate\Database\Capsule\Manager::table('users')
            ->where('id_role', $roleId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        foreach ($userIds as $userId) {
            if (is_string($userId) && !empty($userId)) {
                $this->jwtService->invalidateUserTokens($userId);
            }
        }
    }

    /**
     * @param string $id
     * @return Role|array<string, mixed>|null
     */
    public function retrieveById(string $id): Model|array|null
    {
        /** @var Role|null $role */
        $role = $this->repository->find($id);

        return $role;
    }


    /**
     * @param Role $role
     * @param array<int, array<string, mixed>> $permissions
     */
    private function syncFeatures(Role $role, array $permissions): void
    {
        $syncData = [];
        foreach ($permissions as $p) {
            $featureId = $p['id_feature'] ?? null;
            if (!$featureId)
                continue;

            $syncData[$featureId] = [
                'permissions' => json_encode([
                    'create' => $p['create'] ?? false,
                    'view' => $p['view'] ?? false,
                    'delete' => $p['delete'] ?? false,
                    'activate' => $p['activate'] ?? false,
                ])
            ];
        }
        $role->features()->sync($syncData);
    }
}
