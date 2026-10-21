<?php

namespace OguzhanTogay\HueClient\Tests\Api;

use Mockery;
use OguzhanTogay\HueClient\Api\RestApi;
use OguzhanTogay\HueClient\HueClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

class SecurityTest extends TestCase
{
    private HueClient $client;

    protected function setUp(): void
    {
        $this->client = Mockery::mock(HueClient::class);
        $this->client->shouldReceive('lights->getAll')->andReturn([]);
        $this->client->shouldReceive('isConnected')->andReturn(true);
        $this->client->shouldReceive('getBridgeIp')->andReturn('192.168.1.2');
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testRefusesEverythingWhenNoKeyIsConfigured(): void
    {
        $response = $this->send(new RestApi($this->client), 'GET', '/api/lights');

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('HUE_API_KEY', (string) $response->getBody());
    }

    public function testInsecureModeNeedsNoKey(): void
    {
        $api = new RestApi($this->client, ['allow_insecure' => true]);

        $this->assertSame(200, $this->send($api, 'GET', '/api/lights')->getStatusCode());
    }

    public function testRejectsAMissingOrWrongKey(): void
    {
        $api = new RestApi($this->client, ['api_key' => 'right']);

        $this->assertSame(401, $this->send($api, 'GET', '/api/lights')->getStatusCode());
        $this->assertSame(401, $this->send($api, 'GET', '/api/lights', ['Authorization' => 'Bearer wrong'])->getStatusCode());
    }

    public function testAcceptsTheKeyAsBearerOrHeader(): void
    {
        $api = new RestApi($this->client, ['api_key' => 'right']);

        $this->assertSame(200, $this->send($api, 'GET', '/api/lights', ['Authorization' => 'Bearer right'])->getStatusCode());
        $this->assertSame(200, $this->send($api, 'GET', '/api/lights', ['X-API-Key' => 'right'])->getStatusCode());
    }

    public function testHealthIsPublic(): void
    {
        $api = new RestApi($this->client, ['api_key' => 'right']);

        $this->assertSame(200, $this->send($api, 'GET', '/api/health')->getStatusCode());
    }

    public function testNoCorsHeadersForUnlistedOrigins(): void
    {
        $api = new RestApi($this->client, ['api_key' => 'right']);
        $response = $this->send($api, 'GET', '/api/health', ['Origin' => 'https://evil.example']);

        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testCorsForAllowedOriginsIncludingPreflight(): void
    {
        $api = new RestApi($this->client, ['api_key' => 'right', 'cors_origins' => ['https://dash.local']]);

        $response = $this->send($api, 'OPTIONS', '/api/lights/1/state', ['Origin' => 'https://dash.local']);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('https://dash.local', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testWritesMustBeJson(): void
    {
        $api = new RestApi($this->client, ['api_key' => 'right']);
        $response = $this->send(
            $api,
            'POST',
            '/api/schedules',
            ['Authorization' => 'Bearer right', 'Content-Type' => 'text/plain'],
            '{"name":"x"}'
        );

        $this->assertSame(415, $response->getStatusCode());
    }

    public function testBodylessWritesNeedNoContentType(): void
    {
        $this->client->shouldReceive('scenes->activate')->with('abc')->andReturn(true);
        $api = new RestApi($this->client, ['api_key' => 'right']);

        // Like php://input under PHP's built-in server: empty, size unknown.
        $body = new \GuzzleHttp\Psr7\PumpStream(static fn () => false);
        $request = new Request('PUT', new Uri('http', 'localhost', 8080, '/api/scenes/abc/activate'), new Headers(['Authorization' => 'Bearer right']), [], ['REMOTE_ADDR' => '127.0.0.1'], $body);

        $this->assertNotSame(415, $api->getApp()->handle($request)->getStatusCode());
    }

    public function testOptionsFromEnvironment(): void
    {
        putenv('HUE_API_KEY=k');
        putenv('HUE_API_INSECURE=yes');
        putenv('HUE_CORS_ORIGINS=https://a.local, https://b.local');
        putenv('HUE_DEBUG');

        try {
            $this->assertSame([
                'api_key' => 'k',
                'allow_insecure' => true,
                'cors_origins' => ['https://a.local', 'https://b.local'],
                'debug' => false,
                'webhooks' => [
                    'github_secret' => null, 'sentry_secret' => null, 'lights' => [], 'branches' => [], 'state_dir' => null,
                ],
            ], RestApi::optionsFromEnvironment());
        } finally {
            putenv('HUE_API_KEY');
            putenv('HUE_API_INSECURE');
            putenv('HUE_CORS_ORIGINS');
        }
    }

    private function send(RestApi $api, string $method, string $path, array $headers = [], string $body = ''): ResponseInterface
    {
        $stream = (new StreamFactory())->createStream($body);
        $request = new Request($method, new Uri('http', 'localhost', 8080, $path), new Headers($headers), [], ['REMOTE_ADDR' => '127.0.0.1'], $stream);

        return $api->getApp()->handle($request);
    }
}
