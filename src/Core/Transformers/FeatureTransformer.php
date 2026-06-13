<?php

declare(strict_types=1);

namespace App\Core\Transformers;

use App\Modules\Feature\Feature;
use Illuminate\Database\Eloquent\Model;

class FeatureTransformer extends BaseTransformer
{
    /**
     * @param Model $model
     * @return array<string, mixed>
     */
    public function transform(Model $model): array
    {
        assert($model instanceof \App\Modules\Feature\Feature);
        return [
            'id' => $model->id,
            'name' => $model->name,
            'description' => $model->description,
            'active' => (bool)$model->active,
            'created_at' => $model->created_at?->toIso8601String(),
            'updated_at' => $model->updated_at?->toIso8601String(),
        ];
    }
}
