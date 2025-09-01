<?php

namespace OguzhanTogay\HueClient\Tests\Api\Middleware;

use PHPUnit\Framework\TestCase;
use OguzhanTogay\HueClient\Api\Middleware\RateLimitMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;

class RateLimitMiddlewareTest extends TestCase
{
    private RateLimitMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RateLimitMiddleware();
    }

    public function testAllowsRequestsUnderLimit(): void
    {
        $request = $this->createRequest('GET', '/api/lights');
        $handler = $this->createMockHandler(new Response());

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Remaining'));
    }

    public function testSetsCorrectHeaders(): void
    {
        $request = $this->createRequest('GET', '/api/lights');
        $handler = $this->createMockHandler(new Response());

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals('100', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertNotEmpty($response->getHeaderLine('X-RateLimit-Remaining'));
    }

    private function createRequest(string $method, string $path, array $headers = [], string $body = ''): Request
    {
        $uri = new Uri('http', 'localhost', 8080, $path);
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, $body);
        rewind($handle);
        $stream = (new StreamFactory())->createStreamFromResource($handle);

        return new Request($method, $uri, new Headers($headers), [], [], $stream);
    }

    private function createMockHandler(Response $response): RequestHandlerInterface
    {
        return new class($response) implements RequestHandlerInterface {
            public function __construct(private Response $response) {}
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return $this->response;
            }
        };
    }
}