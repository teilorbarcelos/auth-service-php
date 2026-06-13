<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use App\Core\BaseController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

class DashboardController extends BaseController
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {
        $this->service = $dashboardService;
    }

    #[OA\Get(
        path: "/dashboard/stats",
        summary: "Get dashboard stats",
        tags: ["Dashboard"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "createdAt_start", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "createdAt_end", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Dashboard statistics",
                content: new OA\JsonContent(ref: "#/components/schemas/DashboardStatsResponse")
            ),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function getStats(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $createdAtStart = $queryParams['createdAt_start'] ?? null;
        $createdAtEnd = $queryParams['createdAt_end'] ?? null;

        if (is_array($createdAtStart)) {
            $createdAtStart = reset($createdAtStart);
        }
        if (is_array($createdAtEnd)) {
            $createdAtEnd = reset($createdAtEnd);
        }

        $stats = $this->dashboardService->getStats(
            $createdAtStart !== null ? (string)$createdAtStart : null,
            $createdAtEnd !== null ? (string)$createdAtEnd : null
        );

        return $this->jsonResponse($response, $stats);
    }
}
