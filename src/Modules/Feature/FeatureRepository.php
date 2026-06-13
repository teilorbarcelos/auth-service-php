<?php

declare(strict_types=1);

namespace App\Modules\Feature;

use App\Core\BaseRepository;
use App\Modules\Feature\Feature;

/**
 * @extends BaseRepository<Feature>
 */
class FeatureRepository extends BaseRepository
{
    protected string $modelClass = Feature::class;
}
