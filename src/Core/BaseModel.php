<?php

declare(strict_types=1);

namespace App\Core;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static \Illuminate\Database\Eloquent\Builder<static> where(string|array|\Closure $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static> orWhere(string|array|\Closure $column, mixed $operator = null, mixed $value = null)
 * @method static static|null find(string $id)
 * @method static static|null first()
 * @method static static create(array<string, mixed> $attributes)
 * @method static static firstOrCreate(array<string, mixed> $attributes, array<string, mixed> $values = [])
 */
abstract class BaseModel extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

}
