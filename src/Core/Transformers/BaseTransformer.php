<?php

declare(strict_types=1);

namespace App\Core\Transformers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class BaseTransformer
{
    /**
     * @param Model $model
     * @return array<string, mixed>
     */
    abstract public function transform(Model $model): array;

    /**
     * @param \Illuminate\Support\Collection<int, Model>|array<int, Model> $collection
     * @return array<int, array<string, mixed>>
     */
    public function transformCollection(mixed $collection): array
    {
        if (is_array($collection)) {
            $collection = collect($collection);
        }

        $mapped = [];
        /** @var Model $model */
        foreach ($collection as $model) {
            $mapped[] = $this->transform($model);
        }

        return $mapped;
    }
}
