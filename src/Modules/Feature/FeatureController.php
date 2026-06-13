<?php

declare(strict_types=1);

namespace App\Modules\Feature;

use App\Core\BaseController;
use App\Modules\Feature\FeatureService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

class FeatureController extends BaseController
{
    public function __construct(
        protected FeatureService $featureService
    ) {
        $this->service = $featureService;
    }

    #[OA\Get(
        path: "/features",
        summary: "List features (paginated)",
        tags: ["Features"],
        security: [["bearerAuth" => []]],
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: "#/components/schemas/PaginatedFeature"))]
    )]
    public function listItems(Request $request, Response $response): Response
    {
        return parent::listItems($request, $response);
    }

    #[OA\Get(
        path: "/features/all",
        summary: "List all features",
        tags: ["Features"],
        security: [["bearerAuth" => []]],
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: "#/components/schemas/PaginatedFeature"))]
    )]
    public function listAllItems(Request $request, Response $response): Response
    {
        return parent::listAllItems($request, $response);
    }

    #[OA\Get(
        path: "/features/{id}",
        summary: "Get feature by ID",
        tags: ["Features"],
        security: [["bearerAuth" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))],
        responses: [new OA\Response(response: 200, description: "OK", content: new OA\JsonContent(ref: "#/components/schemas/Feature"))]
    )]
    public function getById(Request $request, Response $response, array $args): Response
    {
        return parent::getById($request, $response, $args);
    }
}
