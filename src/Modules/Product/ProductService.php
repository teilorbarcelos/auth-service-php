<?php

declare(strict_types=1);

namespace App\Modules\Product;

use App\Core\BaseService;
use App\Infrastructure\Auth\UserSession;
use App\Modules\Product\ProductRepository;


use App\Modules\Product\Product;
use Illuminate\Database\Eloquent\Model;
use Respect\Validation\Validator as v;

/**
 * @extends BaseService<Product, ProductRepository>
 */
class ProductService extends BaseService
{
    protected array $filterableFields = ['name', 'active'];
    protected array $searchableFields = ['name', 'description'];

    public function __construct(
        protected ProductRepository $productRepository,
        protected UserSession $userSession
    ) {
        $this->repository = $productRepository;
    }

    public function create(array $data): Model|array
    {
        $this->validate($data, [
            'sku' => v::stringType()->notEmpty()->length(3, 100),
            'name' => v::stringType()->notEmpty()->length(3, 255),
            'category' => v::optional(v::stringType()->notEmpty()->length(3, 255)),
            'description' => v::optional(v::stringType()->notEmpty()),
            'price' => v::numericVal()->positive(),
            'stock' => v::optional(v::intVal()->min(0)),
        ]);
        if (!isset($data['id_user'])) {
            $data['id_user'] = $this->userSession->getUserId();
        }
        return parent::create($data);
    }

    public function update(string $id, array $data): Model|array|null
    {
        $this->validate($data, [
            'sku' => v::optional(v::stringType()->notEmpty()->length(3, 100)),
            'name' => v::optional(v::stringType()->notEmpty()->length(3, 255)),
            'category' => v::optional(v::stringType()->notEmpty()->length(3, 255)),
            'description' => v::optional(v::stringType()->notEmpty()),
            'price' => v::optional(v::numericVal()->positive()),
            'stock' => v::optional(v::intVal()->min(0)),
            'active' => v::optional(v::boolType()),
        ]);
        return parent::update($id, $data);
    }
}
