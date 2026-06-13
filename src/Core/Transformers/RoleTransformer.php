<?php

declare(strict_types=1);

namespace App\Core\Transformers;

use App\Modules\Role\Role;
use Illuminate\Database\Eloquent\Model;

class RoleTransformer extends BaseTransformer
{
    /**
     * @param Model $model
     * @return array<string, mixed>
     */
    public function transform(Model $model): array
    {
        /** @var Role $model */
        $model->loadMissing('features');

        $permissions = $model->features->map(function ($feature) {
            /** @var \App\Modules\Feature\Feature $feature */
            $perms = isset($feature->pivot->permissions) ? json_decode((string)$feature->pivot->permissions, true) : [];
            if (!is_array($perms)) {
                $perms = [];
            }
            return [
                'id_role' => $feature->pivot->id_role,
                'id_feature' => $feature->id,
                'create' => $perms['create'] ?? false,
                'view' => $perms['view'] ?? false,
                'delete' => $perms['delete'] ?? false,
                'activate' => $perms['activate'] ?? false
            ];
        })->toArray();

        $roleData = $model->toArray();
        unset($roleData['features']);
        $roleData['is_deleted'] = isset($roleData['is_deleted']) ? (bool)$roleData['is_deleted'] : false;

        $roleData['RoleFeature'] = $permissions;

        return $roleData;
    }
}
