<?php

namespace OguzhanTogay\HueClient\Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

class RateLimitMiddleware implements MiddlewareInterface
{
    private RateLimiterFactory $rateLimiterFactory;

    public function __construct()
    {
        $this->rateLimiterFactory = new RateLimiterFactory([
            'id' => 'hue_api',
            'policy' => 'sliding_window',
            'limit' => 100,
            'interval' => '1 minute',
        ], self::storage());
    }

    /**
     * PHP's built-in server rebuilds every object per request, so in-memory
     * counts would reset each time; keep them in the cache when it exists.
     */
    private static function storage(): StorageInterface
    {
        if (class_exists(FilesystemAdapter::class)) {
            return new CacheStorage(new FilesystemAdapter('hue_rate_limit'));
        }

        return new InMemoryStorage();
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $clientIp = $this->getClientIp($request);
        $limiter = $this->rateLimiterFactory->create($clientIp);
        // One token per request: consuming again for the headers would halve the limit.
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write((string) json_encode([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $limit->getRetryAfter()->getTimestamp()
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(429);
        }

        $response = $handler->handle($request);

        return $response->withHeader('X-RateLimit-Limit', (string) $limit->getLimit())
                      ->withHeader('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
    }

    private function getClientIp(Request $request): string
    {
        // Forwarding headers are set by the client, so trusting them would let
        // anyone pick a fresh identity per request. Behind a proxy, have the
        // proxy set REMOTE_ADDR instead.
        return $request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
