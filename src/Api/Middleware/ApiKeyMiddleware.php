<?php

namespace OguzhanTogay\HueClient\Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Requires `Authorization: Bearer <key>` or `X-API-Key: <key>` on every
 * request except the public paths. With no key configured every protected
 * request is refused, unless the server was explicitly started insecure.
 */
class ApiKeyMiddleware implements MiddlewareInterface
{
    /** Paths that never need a key: they expose nothing about the bridge. */
    private const PUBLIC_PATHS = ['/api/health', '/docs', '/api/docs'];

    /** Webhooks authenticate each delivery with their own HMAC signature. */
    private const SIGNED_PREFIX = '/hooks/';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly bool $allowInsecure = false
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $path = $request->getUri()->getPath();
        if (
            $request->getMethod() === 'OPTIONS'
            || in_array($path, self::PUBLIC_PATHS, true)
            || str_starts_with($path, self::SIGNED_PREFIX)
        ) {
            return $handler->handle($request);
        }

        if ($this->apiKey === null || $this->apiKey === '') {
            if ($this->allowInsecure) {
                return $handler->handle($request);
            }

            return $this->deny(
                'The REST API has no API key configured. Set HUE_API_KEY, '
                . 'or HUE_API_INSECURE=1 to run without authentication on a trusted network.'
            );
        }

        $given = $this->presentedKey($request);

        if ($given === null || !hash_equals($this->apiKey, $given)) {
            return $this->deny('Missing or invalid API key.');
        }

        return $handler->handle($request);
    }

    private function presentedKey(Request $request): ?string
    {
        $authorization = $request->getHeaderLine('Authorization');
        if (preg_match('/^Bearer\s+(\S+)$/i', $authorization, $match)) {
            return $match[1];
        }

        $header = $request->getHeaderLine('X-API-Key');

        return $header !== '' ? $header : null;
    }

    private function deny(string $message): Response
    {
        $response = new \Slim\Psr7\Response(401);
        $response->getBody()->write((string) json_encode(['error' => true, 'message' => $message]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('WWW-Authenticate', 'Bearer');
    }
}
