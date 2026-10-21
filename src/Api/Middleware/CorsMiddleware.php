<?php

namespace OguzhanTogay\HueClient\Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Sends CORS headers only for origins on the allow list. With an empty list
 * no browser page on another origin can read the API's answers.
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param string[] $allowedOrigins exact origins such as https://dashboard.local, or '*'
     */
    public function __construct(private readonly array $allowedOrigins = [])
    {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $origin = $request->getHeaderLine('Origin');
        $allowed = $origin !== ''
            && (in_array('*', $this->allowedOrigins, true) || in_array($origin, $this->allowedOrigins, true));

        $response = $request->getMethod() === 'OPTIONS'
            ? new \Slim\Psr7\Response(204)
            : $handler->handle($request);

        if (!$allowed) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-API-Key')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    }
}
