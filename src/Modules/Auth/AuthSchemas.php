<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "LoginRequest",
    properties: [
        new OA\Property(property: "email", type: "string", example: "admin@example.com"),
        new OA\Property(property: "password", type: "string", example: "password123")
    ]
)]
#[OA\Schema(
    schema: "UserAuthInfo",
    properties: [
        new OA\Property(property: "id", type: "string"),
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "email", type: "string"),
        new OA\Property(
            property: "role",
            type: "object",
            properties: [
                new OA\Property(property: "id", type: "string"),
                new OA\Property(property: "name", type: "string"),
                new OA\Property(property: "description", type: "string"),
                new OA\Property(
                    property: "permissions",
                    type: "array",
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: "feature", type: "string"),
                            new OA\Property(property: "create", type: "boolean"),
                            new OA\Property(property: "view", type: "boolean"),
                            new OA\Property(property: "delete", type: "boolean"),
                            new OA\Property(property: "activate", type: "boolean")
                        ]
                    )
                )
            ]
        )
    ]
)]
#[OA\Schema(
    schema: "LoginResponse",
    properties: [
        new OA\Property(property: "token", type: "string"),
        new OA\Property(property: "refreshToken", type: "string"),
        new OA\Property(property: "user", ref: "#/components/schemas/UserAuthInfo")
    ]
)]
class AuthSchemas {}
