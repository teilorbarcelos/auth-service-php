<?php

declare(strict_types=1);

namespace App\Modules\{{MODULE_NAME}};

use App\Core\BaseRepository;
use App\Modules\{{MODULE_NAME}}\{{MODULE_NAME}};

/**
 * @extends BaseRepository<{{MODULE_NAME}}>
 */
class {{MODULE_NAME}}Repository extends BaseRepository
{
    protected string $modelClass = {{MODULE_NAME}}::class;
}
