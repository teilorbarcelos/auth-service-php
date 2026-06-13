<?php

declare(strict_types=1);

namespace App\Modules\{{MODULE_NAME}};

use App\Core\BaseController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

class {{MODULE_NAME}}Controller extends BaseController
{
    public function __construct(
        protected {{MODULE_NAME}}Service ${{MODULE_LOWER}}Service,
        protected \App\Core\Transformers\{{MODULE_NAME}}Transformer ${{MODULE_LOWER}}Transformer
    ) {
        $this->service = ${{MODULE_LOWER}}Service;
        $this->transformer = ${{MODULE_LOWER}}Transformer;
    }

    #[OA\Get(
        path: "/{{MODULE_LOWER_PLURAL}}",
        summary: "List {{MODULE_LOWER_PLURAL}} (paginated)",
        tags: ["{{MODULE_NAME}}"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200, 
                description: "List of {{MODULE_LOWER_PLURAL}}",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/{{MODULE_NAME}}")),
                        new OA\Property(property: "total", type: "integer")
                    ]
                )
            )
        ]
    )]
    public function listItems(Request $request, Response $response): Response
    {
        return parent::listItems($request, $response);
    }

    #[OA\Get(
        path: "/{{MODULE_LOWER_PLURAL}}/all",
        summary: "List all {{MODULE_LOWER_PLURAL}}",
        tags: ["{{MODULE_NAME}}"],
        security: [["bearerAuth" => []]],
        responses: [new OA\Response(response: 200, description: "OK")]
    )]
    public function listAllItems(Request $request, Response $response): Response
    {
        return parent::listAllItems($request, $response);
    }

    #[OA\Get(
        path: "/{{MODULE_LOWER_PLURAL}}/{id}",
        summary: "Get {{MODULE_NAME}} by ID",
        tags: ["{{MODULE_NAME}}"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: "#/components/schemas/{{MODULE_NAME}}"))]
    )]
    public function getById(Request $request, Response $response, array $args): Response
    {
        return parent::getById($request, $response, $args);
    }

    #[OA\Post(
        path: "/{{MODULE_LOWER_PLURAL}}",
        summary: "Create {{MODULE_NAME}}",
        tags: ["{{MODULE_NAME}}"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/{{MODULE_NAME}}")
        ),
        responses: [new OA\Response(response: 201, description: "Created")]
    )]
    public function create(Request $request, Response $response): Response
    {
        return parent::create($request, $response);
    }

    #[OA\Put(
        path: "/{{MODULE_LOWER_PLURAL}}/{id}",
        summary: "Update {{MODULE_NAME}}",
        tags: ["{{MODULE_NAME}}"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/{{MODULE_NAME}}")
        ),
        responses: [new OA\Response(response: 200, description: "OK")]
    )]
    public function update(Request $request, Response $response, array $args): Response
    {
        return parent::update($request, $response, $args);
    }

    #[OA\Delete(
        path: "/{{MODULE_LOWER_PLURAL}}/{id}",
        summary: "Delete {{MODULE_NAME}}",
        tags: ["{{MODULE_NAME}}"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 204, description: "No Content")]
    )]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return parent::delete($request, $response, $args);
    }

    #[OA\Patch(
        path: "/{{MODULE_LOWER_PLURAL}}/{id}/status",
        summary: "Toggle {{MODULE_NAME}} status",
        tags: ["{{MODULE_NAME}}"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [new OA\Property(property: "active", type: "boolean")])
        ),
        responses: [new OA\Response(response: 200, description: "OK")]
    )]
    public function toggleStatus(Request $request, Response $response, array $args): Response
    {
        return parent::toggleStatus($request, $response, $args);
    }
}
