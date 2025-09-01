<?php

namespace OguzhanTogay\HueClient\Api\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

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
        ], new InMemoryStorage());
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $clientIp = $this->getClientIp($request);
        $limiter = $this->rateLimiterFactory->create($clientIp);

        if (!$limiter->consume()->isAccepted()) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $limiter->consume()->getRetryAfter()?->getTimestamp()
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(429);
        }

        $response = $handler->handle($request);
        
        return $response->withHeader('X-RateLimit-Limit', '100')
                      ->withHeader('X-RateLimit-Remaining', (string)$limiter->consume()->getRemainingTokens());
    }

    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $serverParams['HTTP_X_FORWARDED_FOR'])[0];
        }
        
        if (!empty($serverParams['HTTP_X_REAL_IP'])) {
            return $serverParams['HTTP_X_REAL_IP'];
        }
        
        return $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}