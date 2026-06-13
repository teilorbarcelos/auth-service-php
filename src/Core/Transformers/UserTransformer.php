<?php

declare(strict_types=1);

namespace App\Core\Transformers;

use App\Modules\User\User;
use Illuminate\Database\Eloquent\Model;

class UserTransformer extends BaseTransformer
{
    /**
     * @param Model $model
     * @return array<string, mixed>
     */
    public function transform(Model $model): array
    {
        assert($model instanceof \App\Modules\User\User);
        return [
            'id' => $model->id,
            'name' => $model->name,
            'email' => $model->email,
            'active' => (bool)$model->active,
            'id_role' => $model->id_role,
            'created_at' => $model->created_at?->toIso8601String(),
            'updated_at' => $model->updated_at?->toIso8601String()
        ];
    }
}
