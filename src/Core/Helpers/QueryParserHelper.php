<?php

declare(strict_types=1);

namespace App\Core\Helpers;

use App\Core\Exceptions\BadRequestException;

class QueryParserHelper
{
    public const RESERVED_PARAMS = ['page', 'size', 'searchWord', 'searchFields', 'orderBy', 'orderDirection'];

    /**
     * @param array<string, mixed> $query
     * @param array<int, string> $filterableFields
     * @param array<int, string> $searchableFields
     * @return array{andRules: array<int, array<string, mixed>>, orRules: array<int, array<string, mixed>>}
     */
    public static function parseQueryParams(
        array $query,
        array $filterableFields,
        array $searchableFields,
        bool $onlyActive = true
    ): array {
        $rawSize = $query['size'] ?? null;
        $size = is_scalar($rawSize) ? (int)$rawSize : 10;
        if ($size > 100) {
            throw new BadRequestException("O tamanho da página não pode ser maior que 100.");
        }

        $andRules = [];

        foreach ($query as $key => $value) {
            if (in_array($key, self::RESERVED_PARAMS) || $value === null || $value === '') {
                continue;
            }

            [$rule, $originalField] = self::parseRule($key, $value);

            if (!in_array($rule['field'], $filterableFields) && !in_array($key, $filterableFields) && !in_array($originalField, $filterableFields)) {
                throw new BadRequestException("O filtro '{$key}' não é permitido para este recurso.");
            }

            $andRules[] = $rule;
        }

        if ($onlyActive && in_array('active', $filterableFields)) {
            $hasActive = false;
            foreach ($andRules as $rule) {
                if ($rule['field'] === 'active') {
                    $hasActive = true;
                    break;
                }
            }
            if (!$hasActive) {
                [$rule] = self::parseRule('active', true);
                $andRules[] = $rule;
            }
        }

        return [
            'andRules' => $andRules,
            'orRules' => self::parseSearchRules($query, $searchableFields)
        ];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return array{0: array<string, mixed>, 1: string}
     */
    private static function parseRule(string $key, $value): array
    {
        $operator = '=';
        $field = $key;

        if (str_ends_with($key, '_start')) {
            if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                throw new BadRequestException("Formato de data inválido para '{$key}'. Use YYYY-MM-DD.");
            }
            [$y, $m, $d] = explode('-', $value);
            if (!checkdate((int)$m, (int)$d, (int)$y)) {
                throw new BadRequestException("Formato de data inválido para '{$key}'. Use YYYY-MM-DD.");
            }
            $field = substr($key, 0, -6);
            $operator = '>=';
        } elseif (str_ends_with($key, '_end')) {
            if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                throw new BadRequestException("Formato de data inválido para '{$key}'. Use YYYY-MM-DD.");
            }
            [$y, $m, $d] = explode('-', $value);
            if (!checkdate((int)$m, (int)$d, (int)$y)) {
                throw new BadRequestException("Formato de data inválido para '{$key}'. Use YYYY-MM-DD.");
            }
            $field = substr($key, 0, -4);
            $operator = '<=';

            $value .= ' 23:59:59.999';
        }

        $originalField = $field;
        $snakeField = self::camelToSnake($field);

        $relation = null;
        $relField = null;

        if (strpos($snakeField, '.') !== false) {
            [$relation, $relField] = explode('.', $snakeField);
        }

        return [[
            'field' => $snakeField,
            'operator' => $operator,
            'value' => $value,
            'relation' => $relation,
            'relField' => $relField
        ], $originalField];
    }

    /**
     * @param array<string, mixed> $query
     * @param array<int, string> $searchableFields
     * @return array<int, array<string, mixed>>
     */
    private static function parseSearchRules(array $query, array $searchableFields): array
    {
        $searchWord = $query['searchWord'] ?? null;
        $searchFields = $query['searchFields'] ?? null;

        if (!$searchWord || !is_string($searchWord)) {
            return [];
        }

        if (empty($searchableFields)) {
            throw new BadRequestException("A pesquisa global (searchWord) não está habilitada para este recurso.");
        }

        if (!is_string($searchFields) || trim($searchFields) === '') {
            throw new BadRequestException('O parâmetro "searchFields" é obrigatório quando "searchWord" é fornecido.');
        }

        $orRules = [];
        $requestedFields = array_map('trim', explode(',', $searchFields));

        foreach ($requestedFields as $field) {
            [$rule, $originalField] = self::parseRule($field, $searchWord);
            $rule['operator'] = 'LIKE'; // Default for searchWord

            if (!in_array($rule['field'], $searchableFields) && !in_array($field, $searchableFields) && !in_array($originalField, $searchableFields)) {
                throw new BadRequestException("O campo '{$field}' não está disponível para pesquisa global.");
            }
            $orRules[] = $rule;
        }

        return $orRules;
    }

    /**
     * @param string|null $orderBy
     * @param array<int, string> $filterableFields
     * @throws BadRequestException
     */
    public static function validateOrder(?string &$orderBy, array $filterableFields): void
    {
        if (!$orderBy) {
            return;
        }

        $snakeOrderBy = self::camelToSnake($orderBy);

        if ($snakeOrderBy !== 'created_at' && $snakeOrderBy !== 'updated_at') {
            if (!in_array($snakeOrderBy, $filterableFields) && !in_array($orderBy, $filterableFields)) {
                throw new BadRequestException("A ordenação pelo campo '{$orderBy}' não é permitida.");
            }
        }

        $orderBy = $snakeOrderBy;
    }

    private static function camelToSnake(string $input): string
    {
        $result = preg_replace('/(?<!^)[A-Z]/', '_$0', $input);
        return strtolower(is_string($result) ? $result : $input);
    }
}
