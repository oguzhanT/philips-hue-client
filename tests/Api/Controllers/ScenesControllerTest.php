<?php

namespace OguzhanTogay\HueClient\Tests\Api\Controllers;

use PHPUnit\Framework\TestCase;
use Mockery;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Api\Controllers\ScenesController;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;

class ScenesControllerTest extends TestCase
{
    private HueClient $mockClient;
    private ScenesController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(HueClient::class);
        $this->controller = new ScenesController($this->mockClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetAllScenes(): void
    {
        $mockScenes = Mockery::mock(\OguzhanTogay\HueClient\Resources\Scenes::class);
        $mockScene = Mockery::mock(\OguzhanTogay\HueClient\Resources\Scene::class);

        $this->mockClient->shouldReceive('scenes')->andReturn($mockScenes);
        $mockScenes->shouldReceive('getAll')->andReturn([$mockScene]);
        
        $mockScene->shouldReceive('getId')->andReturn('scene1');
        $mockScene->shouldReceive('getName')->andReturn('Sunset');
        $mockScene->shouldReceive('getType')->andReturn('LightScene');
        $mockScene->shouldReceive('getGroup')->andReturn('1');
        $mockScene->shouldReceive('getLights')->andReturn(['1', '2']);
        $mockScene->shouldReceive('getOwner')->andReturn('user123');
        $mockScene->shouldReceive('isRecycle')->andReturn(false);
        $mockScene->shouldReceive('isLocked')->andReturn(false);
        $mockScene->shouldReceive('getPicture')->andReturn(null);
        $mockScene->shouldReceive('getLastUpdated')->andReturn('2023-01-01T00:00:00');

        $request = $this->createRequest('GET', '/api/scenes');
        $response = new Response();

        $result = $this->controller->getAll($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertIsArray($body);
        $this->assertCount(1, $body);
        $this->assertEquals('Sunset', $body[0]['name']);
    }

    public function testGetSpecificScene(): void
    {
        $mockScenes = Mockery::mock(\OguzhanTogay\HueClient\Resources\Scenes::class);
        $mockScene = Mockery::mock(\OguzhanTogay\HueClient\Resources\Scene::class);

        $this->mockClient->shouldReceive('scenes')->andReturn($mockScenes);
        $mockScenes->shouldReceive('get')->with('scene1')->andReturn($mockScene);
        
        $mockScene->shouldReceive('getId')->andReturn('scene1');
        $mockScene->shouldReceive('getName')->andReturn('Sunrise');
        $mockScene->shouldReceive('getType')->andReturn('LightScene');
        $mockScene->shouldReceive('getGroup')->andReturn('2');
        $mockScene->shouldReceive('getLights')->andReturn(['1', '3']);
        $mockScene->shouldReceive('getOwner')->andReturn('user456');
        $mockScene->shouldReceive('isRecycle')->andReturn(true);
        $mockScene->shouldReceive('isLocked')->andReturn(false);
        $mockScene->shouldReceive('getPicture')->andReturn('picture.jpg');
        $mockScene->shouldReceive('getLastUpdated')->andReturn('2023-01-01T12:00:00');
        $mockScene->shouldReceive('getLightStates')->andReturn(['1' => ['on' => true]]);

        $request = $this->createRequest('GET', '/api/scenes/scene1');
        $response = new Response();

        $result = $this->controller->get($request, $response, ['id' => 'scene1']);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertEquals('Sunrise', $body['name']);
        $this->assertEquals('scene1', $body['id']);
    }

    public function testActivateScene(): void
    {
        $mockScenes = Mockery::mock(\OguzhanTogay\HueClient\Resources\Scenes::class);

        $this->mockClient->shouldReceive('scenes')->andReturn($mockScenes);
        $mockScenes->shouldReceive('activate')->with('scene1')->once();

        $request = $this->createRequest('PUT', '/api/scenes/scene1/activate');
        $response = new Response();

        $result = $this->controller->activate($request, $response, ['id' => 'scene1']);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['success']);
    }

    public function testCreateScene(): void
    {
        $mockScenes = Mockery::mock(\OguzhanTogay\HueClient\Resources\Scenes::class);
        $mockScene = Mockery::mock(\OguzhanTogay\HueClient\Resources\Scene::class);

        $this->mockClient->shouldReceive('scenes')->andReturn($mockScenes);
        $mockScenes->shouldReceive('create')
            ->with('New Scene', [], null)
            ->andReturn($mockScene);
        
        $mockScene->shouldReceive('getId')->andReturn('newscene1');
        $mockScene->shouldReceive('getName')->andReturn('New Scene');

        $payload = json_encode([
            'name' => 'New Scene'
        ]);

        $request = $this->createRequest('POST', '/api/scenes', [], $payload);
        $response = new Response();

        $result = $this->controller->create($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals('New Scene', $body['data']['name']);
    }

    public function testDeleteScene(): void
    {
        $mockScenes = Mockery::mock(\OguzhanTogay\HueClient\Resources\Scenes::class);

        $this->mockClient->shouldReceive('scenes')->andReturn($mockScenes);
        $mockScenes->shouldReceive('delete')->with('scene1')->once();

        $request = $this->createRequest('DELETE', '/api/scenes/scene1');
        $response = new Response();

        $result = $this->controller->delete($request, $response, ['id' => 'scene1']);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['success']);
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