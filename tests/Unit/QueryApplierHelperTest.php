<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Helpers\QueryApplierHelper;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\TestCase;

class QueryApplierHelperTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&Builder */
    private $builderMock;

    protected function setUp(): void
    {
        $this->builderMock = $this->getMockBuilder(Builder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['where', 'whereHas'])
            ->addMethods(['getConnection'])
            ->getMock();
    }

    public function testApplyFiltersWithStandardField(): void
    {
        $this->builderMock->expects($this->once())
            ->method('where')
            ->with('name', '=', 'John');

        $rules = [
            [
                'field' => 'name',
                'operator' => '=',
                'value' => 'John',
                'relation' => null,
                'relField' => null
            ]
        ];

        QueryApplierHelper::applyFilters($this->builderMock, $rules, []);
    }

    public function testApplyFiltersWithStartSuffix(): void
    {
        $this->builderMock->expects($this->once())
            ->method('where')
            ->with('created_at', '>=', '2024-01-01');

        $rules = [
            [
                'field' => 'created_at',
                'operator' => '>=',
                'value' => '2024-01-01',
                'relation' => null,
                'relField' => null
            ]
        ];

        QueryApplierHelper::applyFilters($this->builderMock, $rules, []);
    }

    public function testApplyFiltersWithEndSuffix(): void
    {
        $this->builderMock->expects($this->once())
            ->method('where')
            ->with('created_at', '<=', '2024-01-31');

        $rules = [
            [
                'field' => 'created_at',
                'operator' => '<=',
                'value' => '2024-01-31',
                'relation' => null,
                'relField' => null
            ]
        ];

        QueryApplierHelper::applyFilters($this->builderMock, $rules, []);
    }

    public function testApplyFiltersWithRelationalField(): void
    {
        $this->builderMock->expects($this->once())
            ->method('whereHas')
            ->with('role', $this->isType('callable'));

        $rules = [
            [
                'field' => 'role.name',
                'operator' => '=',
                'value' => 'Admin',
                'relation' => 'role',
                'relField' => 'name'
            ]
        ];

        QueryApplierHelper::applyFilters($this->builderMock, $rules, []);
    }

    public function testApplyFiltersWithOrRules(): void
    {
        $connectionMock = $this->getMockBuilder(\Illuminate\Database\ConnectionInterface::class)
            ->addMethods(['getDriverName'])
            ->getMockForAbstractClass();
        $connectionMock->method('getDriverName')->willReturn('pgsql');

        $this->builderMock->method('getConnection')->willReturn($connectionMock);

        $this->builderMock->expects($this->once())
            ->method('where')
            ->with($this->isType('callable'));

        $orRules = [
            [
                'field' => 'name',
                'operator' => 'LIKE',
                'value' => 'foo',
                'relation' => null,
                'relField' => null
            ]
        ];

        QueryApplierHelper::applyFilters($this->builderMock, [], $orRules);
    }
}
