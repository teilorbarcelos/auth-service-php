<?php

declare(strict_types=1);

namespace App\Modules\{{MODULE_NAME}};

use App\Core\BaseModel;
use OpenApi\Attributes as OA;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
#[OA\Schema(
    schema: "{{MODULE_NAME}}",
    title: "{{MODULE_NAME}}",
    properties: [
        new OA\Property(property: "id", type: "string"),
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "description", type: "string"),
        new OA\Property(property: "active", type: "boolean")
    ]
)]
class {{MODULE_NAME}} extends BaseModel
{
    use SoftDeletes;

    protected $table = '{{TABLE_NAME}}';

    protected $fillable = [
        'id',
        'name',
        'description',
        'active',
        'is_deleted',
        'deleted_at'
    ];
}
