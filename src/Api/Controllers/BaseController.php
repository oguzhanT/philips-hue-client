<?php

namespace OguzhanTogay\HueClient\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use OguzhanTogay\HueClient\HueClient;

abstract class BaseController
{
    protected HueClient $hueClient;

    public function __construct(HueClient $hueClient)
    {
        $this->hueClient = $hueClient;
    }

    protected function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    protected function errorResponse(Response $response, string $message, int $status = 400): Response
    {
        return $this->jsonResponse($response, [
            'error' => true,
            'message' => $message,
            'timestamp' => date('c')
        ], $status);
    }

    protected function successResponse(Response $response, $data = null, string $message = 'Success'): Response
    {
        $responseData = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c')
        ];

        if ($data !== null) {
            $responseData['data'] = $data;
        }

        return $this->jsonResponse($response, $responseData);
    }

    protected function validateJsonPayload(array $payload, array $required = []): ?string
    {
        foreach ($required as $field) {
            if (!isset($payload[$field])) {
                return "Missing required field: {$field}";
            }
        }
        return null;
    }
}