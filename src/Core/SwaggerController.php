<?php

declare(strict_types=1);

namespace App\Core;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

#[OA\Info(title: "Backend PHP Slim API", version: "1.0.0")]
#[OA\SecurityScheme(
  securityScheme: "bearerAuth",
  type: "http",
  name: "bearerAuth",
  in: "header",
  scheme: "bearer"
)]
class SwaggerController extends BaseController
{
  public static bool $forceError = false;

  public function json(Request $request, Response $response): Response
  {
    if (self::$forceError) {
      return $response->withStatus(500);
    }

    $level = error_reporting();
    error_reporting($level & ~E_DEPRECATED);
    $openapi = \OpenApi\Generator::scan([
      __DIR__,
      __DIR__ . '/../Modules'
    ]);
    error_reporting($level);
    /** @var \OpenApi\Annotations\OpenApi $openapi */

    $openapi->servers = [new \OpenApi\Annotations\Server(['url' => '/v1'])];

    $response->getBody()->write($openapi->toJson());
    return $response->withHeader('Content-Type', 'application/json');
  }

  public function ui(Request $request, Response $response): Response
  {
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Swagger UI</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" >
    <style>
      html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
      *, *:before, *:after { box-sizing: inherit; }
      body { margin:0; background: #fafafa; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"> </script>
    <script>
    window.onload = function() {
      window.ui = SwaggerUIBundle({
        url: "/v1/swagger.json",
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
          SwaggerUIBundle.presets.apis,
        ],
        layout: "BaseLayout"
      });
    };
    </script>
</body>
</html>
HTML;
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
  }
}
