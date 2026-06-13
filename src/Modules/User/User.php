<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Core\BaseModel;
use App\Modules\Role\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $id_role
 * @property bool $active
 * @property bool $is_deleted
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \App\Modules\Role\Role|null $role
 * @property \App\Modules\User\UserAuth|null $auth
 */
class User extends BaseModel
{
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'id',
        'name',
        'email',
        'active',
        'id_role',
        'is_deleted',
        'deleted_at'
    ];

    public function auth(): HasOne
    {
        return $this->hasOne(UserAuth::class, 'id', 'id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_role');
    }
}
