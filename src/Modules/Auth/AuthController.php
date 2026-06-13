<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\BaseController;
use App\Modules\Auth\AuthService;
use App\Core\Exceptions\BadRequestException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends BaseController
{
    public function __construct(
        private AuthService $authService
    ) {}

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

    public function me(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        if (!is_string($userId) && !is_numeric($userId)) {
            throw new BadRequestException('Invalid user ID type');
        }
        $result = $this->authService->getMe((string)$userId);
        return $this->jsonResponse($response, $result);
    }

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

    public function logout(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        if (!is_string($userId) && !is_numeric($userId)) {
            throw new BadRequestException('Invalid user ID type');
        }
        $this->authService->logout((string)$userId);
        return $this->jsonResponse($response, [
            'message' => 'Logout successful',
            'valid' => true,
        ]);
    }

    public function jwks(Request $request, Response $response): Response
    {
        $payload = json_encode(['keys' => []]);
        $response->getBody()->write($payload ?: '{}');
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function requestPasswordReset(Request $request, Response $response): Response
    {
        $body = (array)$request->getParsedBody();
        $email = (string)($body['email'] ?? '');

        $token = $this->authService->requestPasswordReset($email);

        $response->getBody()->write((string)json_encode([
            'message' => 'Password reset token generated',
            'token' => $token,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function validateResetToken(Request $request, Response $response): Response
    {
        $body = (array)$request->getParsedBody();
        $email = (string)($body['email'] ?? '');
        $token = (string)($body['token'] ?? '');

        $this->authService->validateResetToken($email, $token);

        $response->getBody()->write((string)json_encode(['valid' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function changePassword(Request $request, Response $response): Response
    {
        $body = (array)$request->getParsedBody();
        $email = (string)($body['email'] ?? '');
        $token = (string)($body['token'] ?? '');
        $password = (string)($body['password'] ?? '');

        $this->authService->changePassword($email, $token, $password);

        $response->getBody()->write((string)json_encode(['message' => 'Senha alterada com sucesso!']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
