<?php

namespace OguzhanTogay\HueClient\Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Writes must be sent as application/json. A browser can post text/plain to
 * any address without asking first, so accepting it would let any web page
 * change lights or schedule bridge commands.
 */
class JsonBodyMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            $type = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
            $hasBody = $request->getBody()->getSize() !== 0;

            if ($hasBody && $type !== 'application/json') {
                $response = new \Slim\Psr7\Response(415);
                $response->getBody()->write((string) json_encode([
                    'error' => true,
                    'message' => 'Send the request body as application/json.',
                ]));

                return $response->withHeader('Content-Type', 'application/json');
            }
        }

        return $handler->handle($request);
    }
}
