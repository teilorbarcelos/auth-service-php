<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Helpers\QueryParserHelper;
use App\Core\Exceptions\BadRequestException;
use PHPUnit\Framework\TestCase;

class QueryParserHelperTest extends TestCase
{
    public function testParseQueryParamsWithValidData(): void
    {
        $query = [
            'name' => 'Test',
            'page' => '1',
            'size' => '10'
        ];
        $filterable = ['name', 'active'];
        $searchable = ['name'];

        $result = QueryParserHelper::parseQueryParams($query, $filterable, $searchable);

        $this->assertCount(2, $result['andRules']);
        $this->assertEquals('name', $result['andRules'][0]['field']);
        $this->assertEquals('Test', $result['andRules'][0]['value']);
        $this->assertEquals('active', $result['andRules'][1]['field']);
        $this->assertEquals(true, $result['andRules'][1]['value']);
        $this->assertEmpty($result['orRules']);
    }

    public function testParseQueryParamsThrowsErrorForInvalidFilter(): void
    {
        $this->expectException(BadRequestException::class);
        QueryParserHelper::parseQueryParams(['invalid' => 'val'], ['name'], []);
    }

    public function testParseQueryParamsWithGlobalSearch(): void
    {
        $query = [
            'searchWord' => 'foo',
            'searchFields' => 'name'
        ];
        $filterable = ['active'];
        $searchable = ['name'];

        $result = QueryParserHelper::parseQueryParams($query, $filterable, $searchable);

        $this->assertCount(1, $result['andRules']);
        $this->assertEquals('active', $result['andRules'][0]['field']);
        $this->assertCount(1, $result['orRules']);
        $this->assertEquals('name', $result['orRules'][0]['field']);
        $this->assertEquals('foo', $result['orRules'][0]['value']);
    }

    public function testValidateOrderWithNullOrderBy(): void
    {
        $orderBy = null;
        QueryParserHelper::validateOrder($orderBy, ['name']);
        $this->assertNull($orderBy);
    }

    public function testValidateOrderWithEmptyOrderBy(): void
    {
        $orderBy = '';
        QueryParserHelper::validateOrder($orderBy, ['name']);
        $this->assertEquals('', $orderBy);
    }

    public function testValidateOrderWithValidField(): void
    {
        $orderBy = 'name';
        QueryParserHelper::validateOrder($orderBy, ['name']);
        $this->assertEquals('name', $orderBy);
    }

    public function testValidateOrderWithCreatedAt(): void
    {
        $orderBy = 'created_at';
        QueryParserHelper::validateOrder($orderBy, []);
        $this->assertEquals('created_at', $orderBy);
    }

    public function testValidateOrderWithUpdatedAt(): void
    {
        $orderBy = 'updated_at';
        QueryParserHelper::validateOrder($orderBy, []);
        $this->assertEquals('updated_at', $orderBy);
    }

    public function testParseQueryParamsThrowsErrorForInvalidOrderBy(): void
    {
        $this->expectException(BadRequestException::class);
        $orderBy = 'invalid';
        QueryParserHelper::validateOrder($orderBy, ['name']);
    }

    public function testParseQueryParamsWithDateRangeSuffixes(): void
    {
        $query = [
            'created_at_start' => '2024-01-01',
            'created_at_end' => '2024-01-31'
        ];
        $filterable = ['created_at', 'active'];
        $searchable = [];

        $result = QueryParserHelper::parseQueryParams($query, $filterable, $searchable, false);

        $this->assertCount(2, $result['andRules']);
        $this->assertEquals('>=', $result['andRules'][0]['operator']);
        $this->assertEquals('2024-01-01', $result['andRules'][0]['value']);
        $this->assertEquals('<=', $result['andRules'][1]['operator']);
        $this->assertEquals('2024-01-31 23:59:59.999', $result['andRules'][1]['value']);
    }

    public function testParseQueryParamsWithRelationalDateRange(): void
    {
        $query = [
            'role.created_at_start' => '2024-01-01'
        ];
        $filterable = ['role.created_at'];
        $searchable = [];

        $result = QueryParserHelper::parseQueryParams($query, $filterable, $searchable, false);

        $this->assertCount(1, $result['andRules']);
        $this->assertEquals('role', $result['andRules'][0]['relation']);
        $this->assertEquals('created_at', $result['andRules'][0]['relField']);
        $this->assertEquals('>=', $result['andRules'][0]['operator']);
    }

    public function testParseQueryParamsWithCamelCase(): void
    {
        $query = [
            'createdAt_start' => '2024-01-01'
        ];
        $filterable = ['created_at'];
        $searchable = [];

        $result = QueryParserHelper::parseQueryParams($query, $filterable, $searchable, false);

        $this->assertEquals('created_at', $result['andRules'][0]['field']);
        $this->assertEquals('>=', $result['andRules'][0]['operator']);
    }

    public function testValidateOrderWithCamelCase(): void
    {
        $orderBy = 'createdAt';
        QueryParserHelper::validateOrder($orderBy, ['created_at']);
        $this->assertEquals('created_at', $orderBy);
    }

    public function testParseQueryParamsThrowsErrorForLargeSize(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("O tamanho da página não pode ser maior que 100.");
        QueryParserHelper::parseQueryParams(['size' => '101'], ['active'], []);
    }

    public function testParseQueryParamsThrowsErrorForInvalidStartDate(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("Formato de data inválido para 'created_at_start'. Use YYYY-MM-DD.");
        QueryParserHelper::parseQueryParams(['created_at_start' => 'invalid-date'], ['created_at'], [], false);
    }

    public function testParseQueryParamsThrowsErrorForNonExistentStartDate(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("Formato de data inválido para 'created_at_start'. Use YYYY-MM-DD.");
        QueryParserHelper::parseQueryParams(['created_at_start' => '2024-02-30'], ['created_at'], [], false);
    }

    public function testParseQueryParamsThrowsErrorForInvalidEndDate(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("Formato de data inválido para 'created_at_end'. Use YYYY-MM-DD.");
        QueryParserHelper::parseQueryParams(['created_at_end' => 'invalid-date'], ['created_at'], [], false);
    }

    public function testParseQueryParamsThrowsErrorForNonExistentEndDate(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage("Formato de data inválido para 'created_at_end'. Use YYYY-MM-DD.");
        QueryParserHelper::parseQueryParams(['created_at_end' => '2024-02-30'], ['created_at'], [], false);
    }
}
