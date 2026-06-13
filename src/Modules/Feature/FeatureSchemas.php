<?php

declare(strict_types=1);

namespace App\Modules\Feature;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Feature",
    title: "Feature",
    properties: [
        new OA\Property(property: "id", type: "string"),
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "description", type: "string"),
        new OA\Property(property: "active", type: "boolean"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time")
    ]
)]
#[OA\Schema(
    schema: "PaginatedFeature",
    properties: [
        new OA\Property(property: "items", type: "array", items: new OA\Items(ref: "#/components/schemas/Feature")),
        new OA\Property(property: "total", type: "integer"),
        new OA\Property(property: "page", type: "integer"),
        new OA\Property(property: "size", type: "integer")
    ]
)]
class FeatureSchemas {}
