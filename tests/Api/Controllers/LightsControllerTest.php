<?php

namespace OguzhanTogay\HueClient\Tests\Api\Controllers;

use PHPUnit\Framework\TestCase;
use Mockery;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Api\Controllers\LightsController;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;

class LightsControllerTest extends TestCase
{
    private HueClient $mockClient;
    private LightsController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(HueClient::class);
        $this->controller = new LightsController($this->mockClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetAllLights(): void
    {
        $mockLights = Mockery::mock(\OguzhanTogay\HueClient\Resources\Lights::class);
        $mockLight = Mockery::mock(\OguzhanTogay\HueClient\Resources\Light::class);
        $mockState = Mockery::mock(\OguzhanTogay\HueClient\Resources\LightState::class);

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
            'brightness' => 254
        ]);

        $request = $this->createRequest('GET', '/api/lights');
        $response = new Response();

        $result = $this->controller->getAll($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertIsArray($body);
        $this->assertCount(1, $body);
        $this->assertEquals('Test Light', $body[0]['name']);
    }

    public function testSetLightState(): void
    {
        $mockLights = Mockery::mock(\OguzhanTogay\HueClient\Resources\Lights::class);
        $mockLight = Mockery::mock(\OguzhanTogay\HueClient\Resources\Light::class);

        $this->mockClient->shouldReceive('lights')->andReturn($mockLights);
        $mockLights->shouldReceive('get')->with(1)->andReturn($mockLight);
        
        $mockLight->shouldReceive('on')->once();
        $mockLight->shouldReceive('setBrightness')->with(75)->once();

        $payload = json_encode([
            'on' => true,
            'brightness' => 191  // 75% of 254
        ]);

        $request = $this->createRequest('PUT', '/api/lights/1/state', [], $payload);
        $response = new Response();

        $result = $this->controller->setState($request, $response, ['id' => '1']);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['success']);
    }

    public function testUpdateLightStateWithColor(): void
    {
        $mockLights = Mockery::mock(\OguzhanTogay\HueClient\Resources\Lights::class);
        $mockLight = Mockery::mock(\OguzhanTogay\HueClient\Resources\Light::class);

        $this->mockClient->shouldReceive('lights')->andReturn($mockLights);
        $mockLights->shouldReceive('get')->with(1)->andReturn($mockLight);
        
        $mockLight->shouldReceive('setBrightness')->with(80)->once();
        $mockLight->shouldReceive('setColor')->with('#FF5733')->once();

        $payload = json_encode([
            'brightness' => 80,
            'color' => '#FF5733'
        ]);

        $request = $this->createRequest('PATCH', '/api/lights/1/state', [], $payload);
        $response = new Response();

        $result = $this->controller->updateState($request, $response, ['id' => '1']);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['success']);
    }

    public function testInvalidJsonPayload(): void
    {
        $request = $this->createRequest('PUT', '/api/lights/1/state', [], 'invalid json');
        $response = new Response();

        $result = $this->controller->setState($request, $response, ['id' => '1']);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['error']);
        $this->assertEquals('Invalid JSON payload', $body['message']);
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