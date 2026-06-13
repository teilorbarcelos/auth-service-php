<?php

declare(strict_types=1);

namespace App\Modules\Feature;

use App\Core\BaseService;
use App\Modules\Feature\FeatureRepository;
use App\Modules\Feature\Feature;
use Illuminate\Database\Eloquent\Collection;


use Illuminate\Database\Eloquent\Model;
use Respect\Validation\Validator as v;

/**
 * @extends BaseService<Feature, FeatureRepository>
 */
class FeatureService extends BaseService
{
    public function __construct(
        private FeatureRepository $featureRepository
    ) {
        $this->repository = $featureRepository;
        $this->filterableFields = ['name', 'active'];
        $this->searchableFields = ['name', 'description'];
    }

    public function listAll(): Collection
    {
        return $this->featureRepository->all();
    }

    public function create(array $data): Model|array
    {
        $this->validate($data, [
            'id' => v::stringType()->notEmpty()->length(2, 50),
            'name' => v::stringType()->notEmpty()->length(3, 255),
            'description' => v::optional(v::stringType()),
        ]);
        return parent::create($data);
    }

    public function update(string $id, array $data): Model|array|null
    {
        $this->validate($data, [
            'name' => v::optional(v::stringType()->notEmpty()->length(3, 255)),
            'description' => v::optional(v::stringType()),
            'active' => v::optional(v::boolType()),
        ]);
        return parent::update($id, $data);
    }
}
