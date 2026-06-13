<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\BaseService;
use App\Core\Exceptions\BadRequestException;
use PHPUnit\Framework\TestCase;

class BaseServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Core\BaseRepository<\Illuminate\Database\Eloquent\Model> */
    private $repositoryMock;
    private BaseService $service;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(\App\Core\BaseRepository::class);
        
        // Concrete implementation for testing BaseService
        $this->service = new class($this->repositoryMock) extends BaseService {
            /** @param \App\Core\BaseRepository<\Illuminate\Database\Eloquent\Model> $repo */
            public function __construct(\App\Core\BaseRepository $repo) {
                $this->repository = $repo;
                $this->filterableFields = ['name', 'category', 'active'];
                $this->searchableFields = ['name', 'description'];
            }
        };
    }

    private function expectedActiveRule(): array
    {
        return [
            'field' => 'active',
            'operator' => '=',
            'value' => true,
            'relation' => null,
            'relField' => null
        ];
    }

    public function testListItemsDefaultPagination(): void
    {
        $this->repositoryMock->expects($this->once())
            ->method('searchPaginated')
            ->with(0, 10, [$this->expectedActiveRule()], [], 'created_at', 'desc')
            ->willReturn(['items' => [], 'total' => 0, 'page' => 0, 'size' => 10]);

        $result = $this->service->listItems([]);
        $this->assertEquals(0, $result['total']);
    }

    public function testListItemsCustomPaginationAndSorting(): void
    {
        $query = [
            'page' => '2',
            'size' => '20',
            'orderBy' => 'name',
            'orderDirection' => 'asc'
        ];

        $this->repositoryMock->expects($this->once())
            ->method('searchPaginated')
            ->with(2, 20, [$this->expectedActiveRule()], [], 'name', 'asc')
            ->willReturn(['items' => [], 'total' => 0, 'page' => 2, 'size' => 20]);

        $this->service->listItems($query);
    }

    public function testListItemsWithValidFilters(): void
    {
        $query = [
            'name' => 'Test',
            'active' => 'true'
        ];

        $this->repositoryMock->expects($this->once())
            ->method('searchPaginated')
            ->with(0, 10, [
                ['field' => 'name', 'operator' => '=', 'value' => 'Test', 'relation' => null, 'relField' => null],
                ['field' => 'active', 'operator' => '=', 'value' => 'true', 'relation' => null, 'relField' => null]
            ], [], 'created_at', 'desc')
            ->willReturn(['items' => [], 'total' => 0, 'page' => 0, 'size' => 10]);

        $this->service->listItems($query);
    }

    public function testListItemsThrowsErrorForInvalidFilter(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("O filtro 'invalid_field' não é permitido para este recurso.");

        $this->service->listItems(['invalid_field' => 'value']);
    }

    public function testListItemsWithValidGlobalSearch(): void
    {
        $query = [
            'searchWord' => 'foo',
            'searchFields' => 'name,description'
        ];

        $this->repositoryMock->expects($this->once())
            ->method('searchPaginated')
            ->with(0, 10, [$this->expectedActiveRule()], [
                ['field' => 'name', 'operator' => 'LIKE', 'value' => 'foo', 'relation' => null, 'relField' => null],
                ['field' => 'description', 'operator' => 'LIKE', 'value' => 'foo', 'relation' => null, 'relField' => null]
            ], 'created_at', 'desc')
            ->willReturn(['items' => [], 'total' => 0, 'page' => 0, 'size' => 10]);

        $this->service->listItems($query);
    }

    public function testListItemsThrowsErrorWhenSearchFieldsMissing(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('O parâmetro "searchFields" é obrigatório quando "searchWord" é fornecido.');

        $this->service->listItems(['searchWord' => 'foo']);
    }

    public function testListItemsThrowsErrorForInvalidSearchField(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("O campo 'secret' não está disponível para pesquisa global.");

        $this->service->listItems(['searchWord' => 'foo', 'searchFields' => 'name,secret']);
    }

    public function testListItemsSkipsEmptyFilters(): void
    {
        $query = [
            'name' => '',
            'active' => null
        ];

        $this->repositoryMock->expects($this->once())
            ->method('searchPaginated')
            ->with(0, 10, [$this->expectedActiveRule()], [], 'created_at', 'desc')
            ->willReturn(['items' => [], 'total' => 0, 'page' => 0, 'size' => 10]);

        $this->service->listItems($query);
    }

    public function testListItemsThrowsErrorWhenSearchNotEnabled(): void
    {
        $serviceWithoutSearch = new class($this->repositoryMock) extends BaseService {
            /** @param \App\Core\BaseRepository<\Illuminate\Database\Eloquent\Model> $repo */
            public function __construct(\App\Core\BaseRepository $repo) {
                $this->repository = $repo;
                $this->searchableFields = [];
            }
        };

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("A pesquisa global (searchWord) não está habilitada para este recurso.");

        $serviceWithoutSearch->listItems(['searchWord' => 'foo']);
    }

    public function testListItemsWithUpdatedAtOrder(): void
    {
        $query = ['orderBy' => 'updated_at'];

        $this->repositoryMock->expects($this->once())
            ->method('searchPaginated')
            ->with(0, 10, [$this->expectedActiveRule()], [], 'updated_at', 'desc')
            ->willReturn(['items' => [], 'total' => 0, 'page' => 0, 'size' => 10]);

        $this->service->listItems($query);
    }

    public function testListAllItems(): void
    {
        $this->repositoryMock->expects($this->once())
            ->method('searchPaginated')
            ->willReturn(['items' => [], 'total' => 0, 'page' => 0, 'size' => 10]);

        $this->service->listAllItems([]);
    }

    public function testRetrieveById(): void
    {
        $this->repositoryMock->expects($this->once())
            ->method('find')
            ->with('123')
            ->willReturn(null);

        $this->service->retrieveById('123');
    }

    public function testCreate(): void
    {
        $data = ['name' => 'New'];
        $this->repositoryMock->expects($this->once())
            ->method('create')
            ->with($data);

        $this->service->create($data);
    }

    public function testUpdate(): void
    {
        $data = ['name' => 'Updated'];
        $this->repositoryMock->expects($this->once())
            ->method('update')
            ->with('123', $data);

        $this->service->update('123', $data);
    }

    public function testDelete(): void
    {
        $this->repositoryMock->expects($this->once())
            ->method('delete')
            ->with('123')
            ->willReturn(true);

        $this->service->delete('123');
    }

    public function testSetStatus(): void
    {
        $this->repositoryMock->expects($this->once())
            ->method('setStatus')
            ->with('123', true);

        $this->service->setStatus('123', true);
    }

    public function testListItemsThrowsErrorForInvalidOrderBy(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("A ordenação pelo campo 'invalid_field' não é permitida.");

        $this->service->listItems(['orderBy' => 'invalid_field']);
    }
}
