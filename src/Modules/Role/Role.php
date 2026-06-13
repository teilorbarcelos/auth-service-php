<?php

declare(strict_types=1);

namespace App\Modules\Role;

use App\Core\BaseModel;
use App\Modules\Feature\Feature;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Feature\Feature> $features
 */
class Role extends BaseModel
{
    use SoftDeletes;

    protected $table = 'roles';

    protected $fillable = [
        'id',
        'name',
        'description',
        'active',
        'is_deleted',
        'deleted_at'
    ];

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'role_features', 'id_role', 'id_feature')
            ->withPivot('permissions');
    }
}
