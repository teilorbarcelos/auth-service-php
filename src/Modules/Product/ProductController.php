<?php

declare(strict_types=1);

namespace App\Modules\Product;

use App\Core\BaseController;
use App\Modules\Product\ProductService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

class ProductController extends BaseController
{
    private const SCHEMA_PRODUCT = "#/components/schemas/Product";
    private const PATH_PRODUCT_ID = "/products/{id}";
    public function __construct(
        protected ProductService $productService,
        protected \App\Core\Transformers\ProductTransformer $productTransformer
    ) {
        $this->service = $productService;
        $this->transformer = $productTransformer;
    }

    #[OA\Get(
        path: "/products",
        summary: "List products (paginated)",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of products",
                content: new OA\JsonContent(ref: "#/components/schemas/PaginatedProduct")
            )
        ]
    )]
    public function listItems(Request $request, Response $response): Response
    {
        return parent::listItems($request, $response);
    }

    #[OA\Get(
        path: "/products/all",
        summary: "List all products",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: "#/components/schemas/PaginatedProduct"))]
    )]
    public function listAllItems(Request $request, Response $response): Response
    {
        return parent::listAllItems($request, $response);
    }

    #[OA\Get(
        path: self::PATH_PRODUCT_ID,
        summary: "Get product by ID",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: self::SCHEMA_PRODUCT))]
    )]
    public function getById(Request $request, Response $response, array $args): Response
    {
        return parent::getById($request, $response, $args);
    }

    #[OA\Post(
        path: "/products",
        summary: "Create product",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: self::SCHEMA_PRODUCT)
        ),
        responses: [new OA\Response(response: 201, description: "Created", content: new OA\JsonContent(ref: self::SCHEMA_PRODUCT))]
    )]
    public function create(Request $request, Response $response): Response
    {
        return parent::create($request, $response);
    }

    #[OA\Put(
        path: self::PATH_PRODUCT_ID,
        summary: "Update product",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: self::SCHEMA_PRODUCT)
        ),
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: self::SCHEMA_PRODUCT))]
    )]
    public function update(Request $request, Response $response, array $args): Response
    {
        return parent::update($request, $response, $args);
    }

    #[OA\Delete(
        path: self::PATH_PRODUCT_ID,
        summary: "Delete product",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 204, description: "No Content")]
    )]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return parent::delete($request, $response, $args);
    }

    #[OA\Patch(
        path: "/products/{id}/status",
        summary: "Toggle product status",
        tags: ["Products"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [new OA\Property(property: "active", type: "boolean")])
        ),
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: self::SCHEMA_PRODUCT))]
    )]
    public function toggleStatus(Request $request, Response $response, array $args): Response
    {
        return parent::toggleStatus($request, $response, $args);
    }
}
