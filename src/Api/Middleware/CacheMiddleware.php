<?php

namespace OguzhanTogay\HueClient\Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;

class CacheMiddleware implements MiddlewareInterface
{
    private FilesystemAdapter $cache;
    private array $cacheableRoutes = [
        '/api/lights' => 10,        // 10 seconds
        '/api/groups' => 30,        // 30 seconds
        '/api/rooms' => 30,         // 30 seconds
        '/api/zones' => 30,         // 30 seconds
        '/api/scenes' => 60,        // 1 minute
        '/api/schedules' => 60,     // 1 minute
        '/api/sensors' => 5,        // 5 seconds
        '/api/bridge/info' => 300,  // 5 minutes
        '/api/bridge/config' => 300 // 5 minutes
    ];

    public function __construct()
    {
        $this->cache = new FilesystemAdapter('hue_api', 0, sys_get_temp_dir() . '/hue_cache');
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // Only cache GET requests
        if ($method !== 'GET') {
            return $handler->handle($request);
        }

        $cacheKey = $this->getCacheKey($request);
        $cacheDuration = $this->getCacheDuration($path);

        if ($cacheDuration === null) {
            return $handler->handle($request);
        }

        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            $cachedData = $cacheItem->get();
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write($cachedData);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('X-Cache', 'HIT')
                ->withHeader('Cache-Control', "public, max-age={$cacheDuration}");
        }

        $response = $handler->handle($request);

        if ($response->getStatusCode() === 200) {
            $body = (string) $response->getBody();
            $cacheItem->set($body);
            $cacheItem->expiresAfter($cacheDuration);
            $this->cache->save($cacheItem);
            
            $response = $response->withHeader('X-Cache', 'MISS')
                                ->withHeader('Cache-Control', "public, max-age={$cacheDuration}");
        }

        return $response;
    }

    private function getCacheKey(Request $request): string
    {
        $path = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();
        return md5($path . $query);
    }

    private function getCacheDuration(string $path): ?int
    {
        foreach ($this->cacheableRoutes as $route => $duration) {
            if (strpos($path, $route) === 0) {
                return $duration;
            }
        }
        return null;
    }

    public function clearCache(): void
    {
        $this->cache->clear();
    }
}