<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\BaseController;
use App\Modules\Auth\AuthService;
use App\Core\Exceptions\BadRequestException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

class AuthController extends BaseController
{
    public function __construct(
        private AuthService $authService
    ) {}

    #[OA\Post(
        path: "/auth/login",
        summary: "Authenticate user and get token",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/LoginRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Login successful",
                content: new OA\JsonContent(ref: "#/components/schemas/LoginResponse")
            ),
            new OA\Response(response: 401, description: "Invalid credentials")
        ]
    )]
    public function login(Request $request, Response $response): Response
    {
        $body = $this->getJsonBody($request);
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        if (!is_string($email) || !is_string($password)) {
            throw new BadRequestException('Invalid input types for login');
        }

        $result = $this->authService->login($email, $password);
        return $this->jsonResponse($response, $result);
    }

    #[OA\Get(
        path: "/auth/me",
        summary: "Get current logged user info",
        tags: ["Auth"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "User info found",
                content: new OA\JsonContent(ref: "#/components/schemas/UserAuthInfo")
            )
        ]
    )]
    public function me(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        if (!is_string($userId) && !is_numeric($userId)) {
            // @codeCoverageIgnoreStart
            throw new BadRequestException('Invalid user ID type');
            // @codeCoverageIgnoreEnd
        }
        $result = $this->authService->getMe((string)$userId);
        return $this->jsonResponse($response, $result);
    }

    #[OA\Post(
        path: "/auth/refresh",
        summary: "Refresh session token",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["refreshToken"],
                properties: [
                    new OA\Property(property: "refreshToken", type: "string")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Token refreshed successfuly",
                content: new OA\JsonContent(ref: "#/components/schemas/LoginResponse")
            ),
            new OA\Response(response: 401, description: "Invalid or expired refresh token")
        ]
    )]
    public function refresh(Request $request, Response $response): Response
    {
        $body = $this->getJsonBody($request);
        $refreshToken = $body['refreshToken'] ?? '';

        if (!is_string($refreshToken)) {
            throw new BadRequestException('Invalid input type for refresh token');
        }
        $result = $this->authService->refreshToken($refreshToken);
        return $this->jsonResponse($response, $result);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    #[OA\Post(path: '/v1/auth/password/request', summary: 'Request Password Reset', tags: ['Auth'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'email', type: 'string')]))]
    #[OA\Response(response: 200, description: 'Email sent')]
    public function requestPasswordReset(Request $request, Response $response): Response
    {
        $body = (array)$request->getParsedBody();
        $email = (string)($body['email'] ?? '');

        $this->authService->requestPasswordReset($email);

        $response->getBody()->write((string)json_encode(['message' => 'E-mail de recuperação enviado com sucesso!']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    #[OA\Post(path: '/v1/auth/password/validate', summary: 'Validate Reset Token', tags: ['Auth'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'token', type: 'string')
    ]))]
    #[OA\Response(response: 200, description: 'Token is valid')]
    public function validateResetToken(Request $request, Response $response): Response
    {
        $body = (array)$request->getParsedBody();
        $email = (string)($body['email'] ?? '');
        $token = (string)($body['token'] ?? '');

        $this->authService->validateResetToken($email, $token);

        $response->getBody()->write((string)json_encode(['valid' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    #[OA\Post(path: '/v1/auth/password/change', summary: 'Change Password (Reset)', tags: ['Auth'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'token', type: 'string'),
        new OA\Property(property: 'password', type: 'string')
    ]))]
    #[OA\Response(response: 200, description: 'Password changed')]
    public function resetPassword(Request $request, Response $response): Response
    {
        $body = (array)$request->getParsedBody();
        $email = (string)($body['email'] ?? '');
        $token = (string)($body['token'] ?? '');
        $password = (string)($body['password'] ?? '');

        $this->authService->resetPassword($email, $token, $password);

        $response->getBody()->write((string)json_encode(['message' => 'Senha alterada com sucesso!']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
