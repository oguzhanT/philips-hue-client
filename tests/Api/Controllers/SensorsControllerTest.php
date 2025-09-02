<?php

namespace OguzhanTogay\HueClient\Tests\Api\Controllers;

use PHPUnit\Framework\TestCase;
use Mockery;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Api\Controllers\SensorsController;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;

class SensorsControllerTest extends TestCase
{
    private HueClient $mockClient;
    private SensorsController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(HueClient::class);
        $this->controller = new SensorsController($this->mockClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetAllSensors(): void
    {
        $mockSensors = Mockery::mock(\OguzhanTogay\HueClient\Resources\Sensors::class);
        $mockSensor = Mockery::mock(\OguzhanTogay\HueClient\Resources\Sensor::class);

        $this->mockClient->shouldReceive('sensors')->andReturn($mockSensors);
        $mockSensors->shouldReceive('getAll')->andReturn([$mockSensor]);
        
        $mockSensor->shouldReceive('getId')->andReturn(1);
        $mockSensor->shouldReceive('getName')->andReturn('Motion Sensor');
        $mockSensor->shouldReceive('getType')->andReturn('ZLLPresence');
        $mockSensor->shouldReceive('getModelId')->andReturn('SML001');
        $mockSensor->shouldReceive('getManufacturer')->andReturn('Philips');
        $mockSensor->shouldReceive('getSwVersion')->andReturn('1.0');
        $mockSensor->shouldReceive('getState')->andReturn(['presence' => false]);
        $mockSensor->shouldReceive('getConfig')->andReturn(['on' => true]);
        $mockSensor->shouldReceive('getUniqueId')->andReturn('00:17:88:01:10:39:38:d4-02-0406');

        $request = $this->createRequest('GET', '/api/sensors');
        $response = new Response();

        $result = $this->controller->getAll($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertIsArray($body);
        $this->assertCount(1, $body);
        $this->assertEquals('Motion Sensor', $body[0]['name']);
    }

    public function testGetSpecificSensor(): void
    {
        $mockSensors = Mockery::mock(\OguzhanTogay\HueClient\Resources\Sensors::class);
        $mockSensor = Mockery::mock(\OguzhanTogay\HueClient\Resources\Sensor::class);

        $this->mockClient->shouldReceive('sensors')->andReturn($mockSensors);
        $mockSensors->shouldReceive('get')->with(1)->andReturn($mockSensor);
        
        $mockSensor->shouldReceive('getId')->andReturn(1);
        $mockSensor->shouldReceive('getName')->andReturn('Temperature Sensor');
        $mockSensor->shouldReceive('getType')->andReturn('ZLLTemperature');
        $mockSensor->shouldReceive('getModelId')->andReturn('SML002');
        $mockSensor->shouldReceive('getManufacturer')->andReturn('Philips');
        $mockSensor->shouldReceive('getSwVersion')->andReturn('1.1');
        $mockSensor->shouldReceive('getState')->andReturn(['temperature' => 2100]);
        $mockSensor->shouldReceive('getConfig')->andReturn(['on' => true]);
        $mockSensor->shouldReceive('getUniqueId')->andReturn('00:17:88:01:10:39:38:d5-02-0402');

        $request = $this->createRequest('GET', '/api/sensors/1');
        $response = new Response();

        $result = $this->controller->get($request, $response, ['id' => '1']);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertEquals('Temperature Sensor', $body['name']);
        $this->assertEquals(1, $body['id']);
    }

    public function testSetSensorState(): void
    {
        $mockSensors = Mockery::mock(\OguzhanTogay\HueClient\Resources\Sensors::class);
        $mockSensor = Mockery::mock(\OguzhanTogay\HueClient\Resources\Sensor::class);

        $this->mockClient->shouldReceive('sensors')->andReturn($mockSensors);
        $mockSensors->shouldReceive('get')->with(1)->andReturn($mockSensor);
        $mockSensor->shouldReceive('updateState')->with(['presence' => true])->once();

        $payload = json_encode([
            'state' => ['presence' => true]
        ]);

        $request = $this->createRequest('PUT', '/api/sensors/1/state', [], $payload);
        $response = new Response();

        $result = $this->controller->setState($request, $response, ['id' => '1']);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['success']);
    }

    public function testInvalidJsonPayload(): void
    {
        $request = $this->createRequest('PUT', '/api/sensors/1/state', [], 'invalid json');
        $response = new Response();

        $result = $this->controller->setState($request, $response, ['id' => '1']);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['error']);
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