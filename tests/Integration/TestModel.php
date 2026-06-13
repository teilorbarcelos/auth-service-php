<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\BaseModel;

/**
 * @property string $id
 * @property string $name
 * @property string $category
 * @property bool $active
 * @property bool $is_deleted
 */
class TestModel extends BaseModel
{
    protected $table = 'test_models';
    /** @var array<int, string> */
    protected $fillable = ['id', 'name', 'category', 'active', 'is_deleted'];
}
