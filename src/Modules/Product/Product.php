<?php

declare(strict_types=1);

namespace App\Modules\Product;

use App\Core\BaseModel;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property float $price
 * @property string $category
 * @property int $stock
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Product extends BaseModel
{
    use SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'id',
        'id_user',
        'sku',
        'name',
        'description',
        'category',
        'price',
        'stock',
        'active',
        'is_deleted',
        'deleted_at'
    ];
}
