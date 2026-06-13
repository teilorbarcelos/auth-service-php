<?php

declare(strict_types=1);

namespace App\Core;
use Illuminate\Database\Eloquent\Model;
use App\Core\Traits\ValidatableTrait;

/**
 * @template T of Model
 * @template R of BaseRepository<T>
 */
abstract class BaseService
{
    use ValidatableTrait;
    /** @var array<int, string> */
    protected array $filterableFields = [];
    /** @var array<int, string> */
    protected array $searchableFields = [];
    /** @var R */
    protected $repository;

    /**
     * @param array<string, mixed> $query
     * @param bool $onlyActive
     * @return array{items: array<T>, total: int, page: int, size: int}
     */
    public function listItems(array $query, bool $onlyActive = true): array
    {
        $page = isset($query['page']) && is_numeric($query['page']) ? (int) $query['page'] : 0;
        $size = isset($query['size']) && is_numeric($query['size']) ? (int) $query['size'] : 10;
        $orderBy = isset($query['orderBy']) && is_string($query['orderBy']) ? $query['orderBy'] : 'created_at';
        $orderDirection = isset($query['orderDirection']) && is_string($query['orderDirection']) ? $query['orderDirection'] : 'desc';

        $parsed = \App\Core\Helpers\QueryParserHelper::parseQueryParams(
            $query,
            $this->filterableFields,
            $this->searchableFields,
            $onlyActive
        );

        \App\Core\Helpers\QueryParserHelper::validateOrder($orderBy, $this->filterableFields);

        return $this->repository->searchPaginated(
            $page,
            $size,
            $parsed['andRules'],
            $parsed['orRules'],
            $orderBy,
            $orderDirection
        );
    }

    /**
     * @param array<string, mixed> $query
     * @return array{items: array<T>, total: int, page: int, size: int}
     */
    public function listAllItems(array $query): array
    {
        return $this->listItems($query, false);
    }

    /** @return T|array<string, mixed>|null */
    public function retrieveById(string $id): Model|array|null
    {
        return $this->repository->find($id);
    }

    /**
     * @param array<string, mixed> $data
     * @return T|array<string, mixed>
     */
    public function create(array $data): Model|array
    {
        return $this->repository->create($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return T|array<string, mixed>|null
     */
    public function update(string $id, array $data): Model|array|null
    {
        return $this->repository->update($id, $data);
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }

    /** @return T|array<string, mixed>|null */
    public function setStatus(string $id, bool $active): Model|array|null
    {
        return $this->repository->setStatus($id, $active);
    }
}
