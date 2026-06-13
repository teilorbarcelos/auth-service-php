<?php

declare(strict_types=1);

namespace App\Core\Helpers;

use Illuminate\Database\Eloquent\Builder;

class QueryApplierHelper
{
    /**
     * @param Builder<*> $query
     * @param array<int, array<string, mixed>> $andRules
     * @param array<int, array<string, mixed>> $orRules
     */
    public static function applyFilters(Builder $query, array $andRules, array $orRules): void
    {
        foreach ($andRules as $rule) {
            self::applyRule($query, $rule, 'and');
        }

        if (!empty($orRules)) {
            $connection = $query->getConnection();
            $driver = method_exists($connection, 'getDriverName') ? $connection->getDriverName() : 'pgsql';
            $likeOperator = $driver === 'sqlite' ? 'LIKE' : 'ILIKE';

            $query->where(function (Builder $q) use ($orRules, $likeOperator) {
                foreach ($orRules as $rule) {
                    $rule['operator'] = $likeOperator;
                    self::applyRule($q, $rule, 'or');
                }
            });
        }
    }

    /**
     * @param Builder<*> $query
     * @param array<string, mixed> $rule
     * @param string $boolean 'and' or 'or'
     */
    private static function applyRule(Builder $query, array $rule, string $boolean): void
    {
        $field = is_string($rule['field']) ? $rule['field'] : '';
        $operator = is_string($rule['operator']) ? $rule['operator'] : '=';
        $value = $rule['value'];
        $relation = is_string($rule['relation']) ? $rule['relation'] : null;
        $relField = is_string($rule['relField']) ? $rule['relField'] : null;

        if ($field === '') {
            return;
        }

        if ($operator === 'LIKE' || $operator === 'ILIKE') {
            $value = is_scalar($value) ? "%{$value}%" : "%%";
        }

        if ($relation) {
            $method = $boolean === 'or' ? 'orWhereHas' : 'whereHas';
            $query->$method($relation, function (Builder $q) use ($relField, $operator, $value) {
                $q->where((string)$relField, $operator, $value);
            });
        } else {
            $method = $boolean === 'or' ? 'orWhere' : 'where';
            $query->$method($field, $operator, $value);
        }
    }
}
