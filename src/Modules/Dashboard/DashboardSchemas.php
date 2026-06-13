<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "TimeSeriesStat",
    properties: [
        new OA\Property(property: "date", type: "string"),
        new OA\Property(property: "count", type: "integer")
    ]
)]
#[OA\Schema(
    schema: "UserProductStat",
    properties: [
        new OA\Property(property: "userId", type: "string", nullable: true),
        new OA\Property(property: "userName", type: "string"),
        new OA\Property(property: "count", type: "integer")
    ]
)]
#[OA\Schema(
    schema: "DashboardStatsResponse",
    properties: [
        new OA\Property(property: "userCreationStats", type: "array", items: new OA\Items(ref: "#/components/schemas/TimeSeriesStat")),
        new OA\Property(property: "productCreationStats", type: "array", items: new OA\Items(ref: "#/components/schemas/TimeSeriesStat")),
        new OA\Property(property: "productsPerUser", type: "array", items: new OA\Items(ref: "#/components/schemas/UserProductStat"))
    ]
)]
class DashboardSchemas {}
