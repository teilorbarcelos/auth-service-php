<?php

declare(strict_types=1);

namespace App\Modules\Product;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Product",
    title: "Product",
    properties: [
        new OA\Property(property: "id", type: "string"),
        new OA\Property(property: "sku", type: "string"),
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "category", type: "string"),
        new OA\Property(property: "description", type: "string"),
        new OA\Property(property: "price", type: "number", format: "float"),
        new OA\Property(property: "stock", type: "integer"),
        new OA\Property(property: "active", type: "boolean"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time")
    ]
)]
#[OA\Schema(
    schema: "PaginatedProduct",
    properties: [
        new OA\Property(property: "items", type: "array", items: new OA\Items(ref: "#/components/schemas/Product")),
        new OA\Property(property: "total", type: "integer"),
        new OA\Property(property: "page", type: "integer"),
        new OA\Property(property: "size", type: "integer")
    ]
)]
class ProductSchemas {}
