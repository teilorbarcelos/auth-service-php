<?php

declare(strict_types=1);

namespace App\Modules\Role;

use App\Core\BaseController;
use App\Modules\Role\RoleService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

class RoleController extends BaseController
{
    private const SCHEMA_ROLE = "#/components/schemas/Role";
    private const PATH_ROLE_ID = "/roles/{id}";
    public function __construct(
        protected RoleService $roleService,
        protected \App\Core\Transformers\RoleTransformer $roleTransformer,
        protected \App\Core\Transformers\FeatureTransformer $featureTransformer
    ) {
        $this->service = $roleService;
        $this->transformer = $roleTransformer;
    }

    #[OA\Get(
        path: "/roles/features",
        summary: "List all features (for role matrix)",
        tags: ["Roles"],
        security: [["bearerAuth" => []]],
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/Feature")))]
    )]
    public function listFeatures(Request $request, Response $response): Response
    {
        $result = $this->roleService->listFeatures();
        return $this->jsonResponse($response, $result, 200, $this->featureTransformer);
    }

    #[OA\Get(
        path: "/roles",
        summary: "List roles (paginated)",
        tags: ["Roles"],
        security: [["bearerAuth" => []]],
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: "#/components/schemas/PaginatedRole"))]
    )]
    public function listItems(Request $request, Response $response): Response
    {
        return parent::listItems($request, $response);
    }

    #[OA\Get(
        path: "/roles/all",
        summary: "List all roles",
        tags: ["Roles"],
        security: [["bearerAuth" => []]],
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: "#/components/schemas/PaginatedRole"))]
    )]
    public function listAllItems(Request $request, Response $response): Response
    {
        return parent::listAllItems($request, $response);
    }

    #[OA\Get(
        path: self::PATH_ROLE_ID,
        summary: "Get role by ID",
        tags: ["Roles"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: self::SCHEMA_ROLE))]
    )]
    public function getById(Request $request, Response $response, array $args): Response
    {
        return parent::getById($request, $response, $args);
    }

    #[OA\Post(
        path: "/roles",
        summary: "Create role",
        tags: ["Roles"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: self::SCHEMA_ROLE)
        ),
        responses: [new OA\Response(response: 201, description: "Created", content: new OA\JsonContent(ref: self::SCHEMA_ROLE))]
    )]
    public function create(Request $request, Response $response): Response
    {
        return parent::create($request, $response);
    }

    #[OA\Put(
        path: self::PATH_ROLE_ID,
        summary: "Update role",
        tags: ["Roles"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: self::SCHEMA_ROLE)
        ),
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: self::SCHEMA_ROLE))]
    )]
    public function update(Request $request, Response $response, array $args): Response
    {
        return parent::update($request, $response, $args);
    }

    #[OA\Delete(
        path: self::PATH_ROLE_ID,
        summary: "Delete role",
        tags: ["Roles"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 204, description: "No Content")]
    )]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return parent::delete($request, $response, $args);
    }
}
