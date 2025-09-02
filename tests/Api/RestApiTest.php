<?php

namespace OguzhanTogay\HueClient\Tests\Api;

use PHPUnit\Framework\TestCase;
use Mockery;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Api\RestApi;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

class RestApiTest extends TestCase
{
    private HueClient $mockClient;
    private RestApi $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(HueClient::class);
        $this->api = new RestApi($this->mockClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testHealthEndpoint(): void
    {
        $this->mockClient->shouldReceive('isConnected')->andReturn(true);
        $this->mockClient->shouldReceive('getBridgeIp')->andReturn('192.168.1.100');

        $request = $this->createRequest('GET', '/api/health');
        $response = $this->api->getApp()->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('healthy', $body['status']);
        $this->assertTrue($body['connected']);
    }

    public function testHealthEndpointWhenDisconnected(): void
    {
        $this->mockClient->shouldReceive('isConnected')->andReturn(false);
        $this->mockClient->shouldReceive('getBridgeIp')->andReturn('192.168.1.100');

        $request = $this->createRequest('GET', '/api/health');
        $response = $this->api->getApp()->handle($request);

        $this->assertEquals(503, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('unhealthy', $body['status']);
        $this->assertFalse($body['connected']);
    }

    public function testLightsEndpoint(): void
    {
        $mockLights = Mockery::mock(\OguzhanTogay\HueClient\Resources\Lights::class);
        $mockLight = Mockery::mock();
        $mockState = Mockery::mock();

        $this->mockClient->shouldReceive('lights')->andReturn($mockLights);
        $mockLights->shouldReceive('getAll')->andReturn([$mockLight]);
        
        $mockLight->shouldReceive('getId')->andReturn(1);
        $mockLight->shouldReceive('getName')->andReturn('Test Light');
        $mockLight->shouldReceive('getType')->andReturn('Extended color light');
        $mockLight->shouldReceive('getState')->andReturn($mockState);
        $mockLight->shouldReceive('getManufacturer')->andReturn('Philips');
        $mockLight->shouldReceive('getModelId')->andReturn('LCT015');
        
        $mockState->shouldReceive('toArray')->andReturn([
            'on' => true,
            'brightness' => 254,
            'hue' => 8402,
            'saturation' => 140
        ]);

        $request = $this->createRequest('GET', '/api/lights');
        $response = $this->api->getApp()->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertCount(1, $body);
        $this->assertEquals('Test Light', $body[0]['name']);
    }

    public function testGroupsEndpoint(): void
    {
        $mockGroups = Mockery::mock(\OguzhanTogay\HueClient\Resources\Groups::class);
        $mockGroup = Mockery::mock();
        $mockState = Mockery::mock();

        $this->mockClient->shouldReceive('groups')->andReturn($mockGroups);
        $mockGroups->shouldReceive('getAll')->andReturn([$mockGroup]);
        
        $mockGroup->shouldReceive('getId')->andReturn(1);
        $mockGroup->shouldReceive('getName')->andReturn('Living Room');
        $mockGroup->shouldReceive('getType')->andReturn('Room');
        $mockGroup->shouldReceive('getLights')->andReturn([1, 2, 3]);
        $mockGroup->shouldReceive('getState')->andReturn($mockState);
        $mockGroup->shouldReceive('getClass')->andReturn('Living room');
        
        $mockState->shouldReceive('toArray')->andReturn([
            'all_on' => true,
            'any_on' => true
        ]);

        $request = $this->createRequest('GET', '/api/groups');
        $response = $this->api->getApp()->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertCount(1, $body);
        $this->assertEquals('Living Room', $body[0]['name']);
    }

    public function testRateLimitMiddleware(): void
    {
        // This would require multiple rapid requests to test the rate limiter
        // For simplicity, we'll just verify the headers are set correctly
        $this->mockClient->shouldReceive('isConnected')->andReturn(true);
        $this->mockClient->shouldReceive('getBridgeIp')->andReturn('192.168.1.100');

        $request = $this->createRequest('GET', '/api/health');
        $response = $this->api->getApp()->handle($request);

        $this->assertTrue($response->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Remaining'));
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
}