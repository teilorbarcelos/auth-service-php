<?php

declare(strict_types=1);

namespace App\Modules\Role;

use App\Core\BaseRepository;
use App\Modules\Role\Role;

/**
 * @extends BaseRepository<Role>
 */
class RoleRepository extends BaseRepository
{
    protected string $modelClass = Role::class;
}
