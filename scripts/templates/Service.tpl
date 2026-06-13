<?php

declare(strict_types=1);

namespace App\Modules\{{MODULE_NAME}};

use App\Core\BaseService;
use App\Modules\{{MODULE_NAME}}\{{MODULE_NAME}};

/**
 * @extends BaseService<{{MODULE_NAME}}, {{MODULE_NAME}}Repository>
 */
class {{MODULE_NAME}}Service extends BaseService
{
    protected array $filterableFields = ['name', 'active'];
    protected array $searchableFields = ['name', 'description'];

    public function __construct(
        protected {{MODULE_NAME}}Repository ${{MODULE_LOWER}}Repository
    ) {
        $this->repository = ${{MODULE_LOWER}}Repository;
    }
}
