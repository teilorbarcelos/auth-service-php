<?php

declare(strict_types=1);

namespace App\Core\Transformers;

use App\Modules\{{MODULE_NAME}}\{{MODULE_NAME}};
use Illuminate\Database\Eloquent\Model;

class {{MODULE_NAME}}Transformer extends BaseTransformer
{
    /**
     * @param Model $model
     * @return array<string, mixed>
     */
    public function transform(Model $model): array
    {
        assert($model instanceof {{MODULE_NAME}});
        return [
            'id' => $model->id,
            'name' => $model->name,
            'description' => $model->description,
            'active' => (bool)$model->active,
            'created_at' => $model->created_at?->toIso8601String(),
            'updated_at' => $model->updated_at?->toIso8601String()
        ];
    }
}
