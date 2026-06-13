<?php

declare(strict_types=1);

namespace App\Modules\Product;

use App\Core\BaseRepository;
use App\Modules\Product\Product;

/**
 * @extends BaseRepository<Product>
 */
class ProductRepository extends BaseRepository
{
    protected string $modelClass = Product::class;
}
