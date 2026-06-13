<?php

declare(strict_types=1);

namespace App\Modules\Role;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Role",
    title: "Role",
    properties: [
        new OA\Property(property: "id", type: "string"),
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "description", type: "string"),
        new OA\Property(property: "active", type: "boolean"),
        new OA\Property(property: "RoleFeature", type: "array", items: new OA\Items(ref: "#/components/schemas/RoleFeature")),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time")
    ]
)]
#[OA\Schema(
    schema: "PaginatedRole",
    properties: [
        new OA\Property(property: "items", type: "array", items: new OA\Items(ref: "#/components/schemas/Role")),
        new OA\Property(property: "total", type: "integer"),
        new OA\Property(property: "page", type: "integer"),
        new OA\Property(property: "size", type: "integer")
    ]
)]
#[OA\Schema(
    schema: "RoleFeature",
    properties: [
        new OA\Property(property: "id_role", type: "string"),
        new OA\Property(property: "id_feature", type: "string"),
        new OA\Property(property: "create", type: "boolean"),
        new OA\Property(property: "view", type: "boolean"),
        new OA\Property(property: "delete", type: "boolean"),
        new OA\Property(property: "activate", type: "boolean")
    ]
)]
class RoleSchemas {}
