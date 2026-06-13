<?php

declare(strict_types=1);

namespace App\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template T of Model
 */
abstract class BaseRepository
{
    /** @var class-string<T> */
    protected string $modelClass;

    /** @return Builder<T> */
    protected function newQuery(): Builder
    {
        return $this->modelClass::query();
    }

    /** @return T|null */
    public function find(string $id): ?Model
    {
        return $this->newQuery()->find($id);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, T> */
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->newQuery()->get();
    }

    /**
     * @param array<int, array<string, mixed>> $andRules
     * @param array<int, array<string, mixed>> $orRules
     * @return array{items: array<T>, total: int, page: int, size: int}
     */
    public function searchPaginated(
        int $page,
        int $size,
        array $andRules = [],
        array $orRules = [],
        string $orderBy = 'created_at',
        string $orderDirection = 'desc'
    ): array {
        $query = $this->newQuery();

        \App\Core\Helpers\QueryApplierHelper::applyFilters($query, $andRules, $orRules);

        $query->orderBy($orderBy, $orderDirection);

        $eloquentPage = $page + 1;
        $paginator = $query->paginate($size, ['*'], 'page', $eloquentPage);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $page,
            'size' => $size
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $andRules
     * @param array<int, array<string, mixed>> $orRules
     * @param array<int, string> $relations
     * @return array<T>
     */
    public function search(
        array $andRules = [],
        array $orRules = [],
        string $orderBy = 'created_at',
        string $orderDirection = 'desc',
        array $relations = []
    ): array {
        $query = $this->newQuery();

        if (!empty($relations)) {
            $query->with($relations);
        }

        \App\Core\Helpers\QueryApplierHelper::applyFilters($query, $andRules, $orRules);

        $query->orderBy($orderBy, $orderDirection);

        return $query->get()->all();
    }

    /**
     * @param array<string, mixed> $data
     * @return T
     */
    public function create(array $data): Model
    {
        return $this->modelClass::create($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return T|null
     */
    public function update(string $id, array $data): ?Model
    {
        $model = $this->find($id);
        if ($model) {
            $model->update($data);
            return $model;
        }
        return null;
    }

    public function delete(string $id): bool
    {
        $model = $this->find($id);
        if ($model) {
            $model->update(['active' => false, 'is_deleted' => true]);
            return (bool) $model->delete();
        }
        return false;
    }

    /** @return T|null */
    public function setStatus(string $id, bool $active): ?Model
    {
        $model = $this->find($id);
        if ($model) {
            $model->update(['active' => $active]);
            return $model;
        }
        return null;
    }
}
