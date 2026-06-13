<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\BaseRepository;
use Tests\WebTestCase;

class BaseRepositoryTest extends WebTestCase
{
    /** @var BaseRepository<\Illuminate\Database\Eloquent\Model> */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Create table
        \Illuminate\Database\Capsule\Manager::schema()->dropIfExists('test_models');
        \Illuminate\Database\Capsule\Manager::schema()->create('test_models', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('category')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        // Define concrete repository using the TestModel
        $this->repository = new class (TestModel::class) extends BaseRepository {
            /** @param class-string<TestModel> $modelClass */
            public function __construct(string $modelClass)
            {
                $this->modelClass = $modelClass;
            }
        };

        // Seed data
        $this->repository->create(['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Item A', 'category' => 'Cat 1', 'active' => true]);
        $this->repository->create(['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Item B', 'category' => 'Cat 1', 'active' => false]);
        $this->repository->create(['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Other', 'category' => 'Cat 2', 'active' => true]);
    }

    private function rule(string $field, $value, string $op = '=', ?string $rel = null, ?string $relF = null): array
    {
        return [
            'field' => $field,
            'operator' => $op,
            'value' => $value,
            'relation' => $rel,
            'relField' => $relF
        ];
    }

    public function testSearchPaginatedBasic(): void
    {
        $result = $this->repository->searchPaginated(0, 10);
        $this->assertCount(3, $result['items']);
        $this->assertEquals(3, $result['total']);
    }

    public function testSearchPaginatedWithFilters(): void
    {
        $result = $this->repository->searchPaginated(0, 10, [$this->rule('category', 'Cat 1')]);
        $this->assertCount(2, $result['items']);
        $this->assertEquals(2, $result['total']);

        $result = $this->repository->searchPaginated(0, 10, [
            $this->rule('category', 'Cat 1'),
            $this->rule('active', false)
        ]);
        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];
        $this->assertInstanceOf(TestModel::class, $item);
        $this->assertEquals('Item B', $item->name);
    }

    public function testSearchPaginatedWithGlobalSearch(): void
    {
        $result = $this->repository->searchPaginated(0, 10, [], [$this->rule('name', 'Item', 'LIKE')]);
        $this->assertCount(2, $result['items']);

        $result = $this->repository->searchPaginated(0, 10, [], [$this->rule('name', 'Other', 'LIKE')]);
        $this->assertCount(1, $result['items']);
    }

    public function testSearchPaginatedExcludesDeleted(): void
    {
        /** @var TestModel $item */
        $item = $this->repository->searchPaginated(0, 10, [$this->rule('name', 'Item A')])['items'][0];
        $this->repository->delete($item->id);

        $result = $this->repository->searchPaginated(0, 10);
        $this->assertCount(2, $result['items']);
        $this->assertEquals(2, $result['total']);
    }

    public function testSearchPaginatedSorting(): void
    {
        $result = $this->repository->searchPaginated(0, 10, [], [], 'name', 'asc');
        $itemA = $result['items'][0];
        $this->assertInstanceOf(TestModel::class, $itemA);
        $this->assertEquals('Item A', $itemA->name);

        $result = $this->repository->searchPaginated(0, 10, [], [], 'name', 'desc');
        $other = $result['items'][0];
        $this->assertInstanceOf(TestModel::class, $other);
        $this->assertEquals('Other', $other->name);
    }

    public function testAll(): void
    {
        $result = $this->repository->all();
        $this->assertCount(3, $result);
    }

    public function testFind(): void
    {
        $item = $this->repository->searchPaginated(0, 10, [$this->rule('name', 'Item A')])['items'][0];
        $this->assertInstanceOf(TestModel::class, $item);
        $found = $this->repository->find($item->id);
        $this->assertInstanceOf(TestModel::class, $found);
        $this->assertEquals('Item A', $found->name);

        $this->assertNull($this->repository->find('00000000-0000-0000-0000-000000000000'));
    }

    public function testUpdate(): void
    {
        $item = $this->repository->searchPaginated(0, 10, [$this->rule('name', 'Item A')])['items'][0];
        $this->assertInstanceOf(TestModel::class, $item);
        $updated = $this->repository->update($item->id, ['name' => 'Updated Name']);
        $this->assertInstanceOf(TestModel::class, $updated);
        $this->assertEquals('Updated Name', $updated->name);

        $this->assertNull($this->repository->update('00000000-0000-0000-0000-000000000000', ['name' => 'Fail']));
    }

    public function testDeleteNotFound(): void
    {
        $this->assertFalse($this->repository->delete('00000000-0000-0000-0000-000000000000'));
    }

    public function testSetStatus(): void
    {
        $item = $this->repository->searchPaginated(0, 10, [$this->rule('name', 'Item A')])['items'][0];
        $this->assertInstanceOf(TestModel::class, $item);
        $updated = $this->repository->setStatus($item->id, false);
        $this->assertInstanceOf(TestModel::class, $updated);
        $this->assertFalse($updated->active);

        $this->assertNull($this->repository->setStatus('00000000-0000-0000-0000-000000000000', true));
    }

    public function testSearchWithNonScalarValue(): void
    {
        // Covers the fallback path for non-scalar search values
        $result = $this->repository->searchPaginated(0, 10, [], [$this->rule('', ['not', 'a', 'string'], 'LIKE')]);
        $this->assertCount(3, $result['items']);
    }
}
