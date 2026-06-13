<?php

declare(strict_types=1);

namespace App\Modules\User;

use App\Core\BaseController;
use App\Modules\User\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

class UserController extends BaseController
{
    private const SCHEMA_USER = "#/components/schemas/User";
    private const PATH_USER_ID = "/users/{id}";
    public function __construct(
        protected UserService $userService,
        protected \App\Core\Transformers\UserTransformer $userTransformer
    ) {
        $this->service = $userService;
        $this->transformer = $userTransformer;
    }

    #[OA\Get(
        path: "/users",
        summary: "List users (paginated)",
        tags: ["Users"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of users",
                content: new OA\JsonContent(ref: "#/components/schemas/PaginatedUser")
            )
        ]
    )]
    public function listItems(Request $request, Response $response): Response
    {
        return parent::listItems($request, $response);
    }

    #[OA\Get(
        path: "/users/all",
        summary: "List all users (no pagination)",
        tags: ["Users"],
        security: [["bearerAuth" => []]],
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: "#/components/schemas/PaginatedUser"))]
    )]
    public function listAllItems(Request $request, Response $response): Response
    {
        return parent::listAllItems($request, $response);
    }

    #[OA\Get(
        path: self::PATH_USER_ID,
        summary: "Get user by ID",
        tags: ["Users"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: self::SCHEMA_USER))]
    )]
    public function getById(Request $request, Response $response, array $args): Response
    {
        return parent::getById($request, $response, $args);
    }

    #[OA\Post(
        path: "/users",
        summary: "Create user",
        tags: ["Users"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: self::SCHEMA_USER)
        ),
        responses: [new OA\Response(response: 201, description: "Created", content: new OA\JsonContent(ref: self::SCHEMA_USER))]
    )]
    public function create(Request $request, Response $response): Response
    {
        return parent::create($request, $response);
    }

    #[OA\Put(
        path: self::PATH_USER_ID,
        summary: "Update user",
        tags: ["Users"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: self::SCHEMA_USER)
        ),
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: self::SCHEMA_USER))]
    )]
    public function update(Request $request, Response $response, array $args): Response
    {
        return parent::update($request, $response, $args);
    }

    #[OA\Delete(
        path: self::PATH_USER_ID,
        summary: "Delete user",
        tags: ["Users"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 204, description: "No Content")]
    )]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return parent::delete($request, $response, $args);
    }

    #[OA\Patch(
        path: "/users/{id}/status",
        summary: "Toggle user status",
        tags: ["Users"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [new OA\Property(property: "active", type: "boolean")])
        ),
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: self::SCHEMA_USER))]
    )]
    public function toggleStatus(Request $request, Response $response, array $args): Response
    {
        return parent::toggleStatus($request, $response, $args);
    }

    #[OA\Get(
        path: "/users/export/pdf",
        summary: "Export users to PDF",
        tags: ["Users"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "PDF report stream",
                content: new OA\MediaType(
                    mediaType: "application/pdf",
                    schema: new OA\Schema(type: "string", format: "binary")
                )
            )
        ]
    )]
    public function exportPdf(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $pdfStream = $this->userService->exportPdf($queryParams);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="usuarios.pdf"')
            ->withBody($pdfStream);
    }
}
