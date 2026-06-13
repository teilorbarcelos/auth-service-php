<?php

declare(strict_types=1);

namespace App\Modules\Feature;

use App\Core\BaseModel;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property bool $active
 * @property bool $is_deleted
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \stdClass $pivot
 */
class Feature extends BaseModel
{
    use SoftDeletes;

    protected $table = 'features';

    protected $fillable = [
        'id',
        'name',
        'description',
        'active',
        'is_deleted',
        'deleted_at'
    ];
}
