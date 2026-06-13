<?php

declare(strict_types=1);

namespace App\Core\Transformers;

use App\Modules\Product\Product;
use Illuminate\Database\Eloquent\Model;

class ProductTransformer extends BaseTransformer
{
    /**
     * @param Model $model
     * @return array<string, mixed>
     */
    public function transform(Model $model): array
    {
        assert($model instanceof \App\Modules\Product\Product);
        return [
            'id' => $model->id,
            'sku' => $model->sku,
            'name' => $model->name,
            'description' => $model->description,
            'category' => $model->category,
            'price' => (float)$model->price,
            'stock' => (int)$model->stock,
            'active' => (bool)$model->active,
            'created_at' => $model->created_at?->toIso8601String(),
            'updated_at' => $model->updated_at?->toIso8601String()
        ];
    }
}
