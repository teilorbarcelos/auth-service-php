<?php

declare(strict_types=1);

namespace App\Core;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class BaseController
{
    /** @var mixed The service instance (override in subclasses with specific type) */
    protected $service;

    protected ?\App\Core\Transformers\BaseTransformer $transformer = null;

    protected const MSG_RECORD_NOT_FOUND = 'Record not found';

    /**
     * @param Response $response
     * @param mixed $data
     * @param int $status
     * @param \App\Core\Transformers\BaseTransformer|null $transformer
     * @return Response
     */
    protected function jsonResponse(
        Response $response,
        mixed $data,
        int $status = 200,
        ?\App\Core\Transformers\BaseTransformer $transformer = null
    ): Response {
        $data = $this->transformResponse($data, $transformer);
        $flags = JSON_UNESCAPED_UNICODE;
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            $flags |= JSON_PRETTY_PRINT;
        }
        $payload = json_encode($data, $flags);
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    protected function transformResponse(mixed $data, ?\App\Core\Transformers\BaseTransformer $transformer = null): mixed
    {
        $activeTransformer = $transformer ?? $this->transformer;
        $result = $data;

        if ($activeTransformer && $data) {
            if ($data instanceof \Illuminate\Database\Eloquent\Model) {
                $result = $activeTransformer->transform($data);
            } elseif ($data instanceof \Illuminate\Database\Eloquent\Collection) {
                $result = $activeTransformer->transformCollection($data);
            } elseif (is_array($data) && isset($data['items']) && (is_array($data['items']) || $data['items'] instanceof \Illuminate\Database\Eloquent\Collection)) {
                $data['items'] = $activeTransformer->transformCollection($data['items']);
                $result = $data;
            }
        }

        return $result;
    }

    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    protected function getJsonBody(Request $request): array
    {
        return (array) ($request->getParsedBody() ?? []);
    }

    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    protected function getQueryParams(Request $request): array
    {
        return $request->getQueryParams();
    }

    public function listItems(Request $request, Response $response): Response
    {
        $params = $this->getQueryParams($request);
        $result = $this->service->listItems($params);
        return $this->jsonResponse($response, $result);
    }

    public function listAllItems(Request $request, Response $response): Response
    {
        $params = $this->getQueryParams($request);
        $result = $this->service->listAllItems($params);
        return $this->jsonResponse($response, $result);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, string> $args
     * @return Response
     */
    public function getById(Request $request, Response $response, array $args): Response
    {
        $result = $this->service->retrieveById($args['id']);
        if (!$result) {
            return $this->jsonResponse($response, ['message' => self::MSG_RECORD_NOT_FOUND], 404);
        }
        return $this->jsonResponse($response, $result);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $this->getJsonBody($request);
        $result = $this->service->create($body);
        return $this->jsonResponse($response, $result, 201);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, string> $args
     * @return Response
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $body = $this->getJsonBody($request);
        $result = $this->service->update($args['id'], $body);
        if (!$result) {
            return $this->jsonResponse($response, ['message' => self::MSG_RECORD_NOT_FOUND], 404);
        }
        return $this->jsonResponse($response, $result);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, string> $args
     * @return Response
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $success = $this->service->delete($args['id']);
        if (!$success) {
            return $this->jsonResponse($response, ['message' => self::MSG_RECORD_NOT_FOUND], 404);
        }
        return $response->withStatus(204);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, string> $args
     * @return Response
     */
    public function toggleStatus(Request $request, Response $response, array $args): Response
    {
        $body = $this->getJsonBody($request);
        $active = (bool) ($body['active'] ?? true);
        $result = $this->service->setStatus($args['id'], $active);
        if (!$result) {
            return $this->jsonResponse($response, ['message' => self::MSG_RECORD_NOT_FOUND], 404);
        }
        return $this->jsonResponse($response, $result);
    }
}
