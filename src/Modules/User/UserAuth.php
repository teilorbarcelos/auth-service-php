<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Core\BaseModel;

/**
 * @property string $id
 * @property string $password
 * @property bool $first_access
 * @property string|null $request_password_token
 * @property string|null $request_password_expiration
 * @property int $retries
 * @property bool $active
 */
class UserAuth extends BaseModel
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'auth';

    protected $fillable = [
        'id',
        'password',
        'first_access',
        'request_password_token',
        'request_password_expiration',
        'retries',
        'active'
    ];

    protected $hidden = [
        'password'
    ];
}
