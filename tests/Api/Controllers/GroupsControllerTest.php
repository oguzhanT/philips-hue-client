<?php

namespace OguzhanTogay\HueClient\Tests\Api\Controllers;

use PHPUnit\Framework\TestCase;
use Mockery;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Api\Controllers\GroupsController;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;

class GroupsControllerTest extends TestCase
{
    private HueClient $mockClient;
    private GroupsController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(HueClient::class);
        $this->controller = new GroupsController($this->mockClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetAllGroups(): void
    {
        $mockGroups = Mockery::mock(\OguzhanTogay\HueClient\Resources\Groups::class);
        $mockGroup = Mockery::mock(\OguzhanTogay\HueClient\Resources\Group::class);
        $mockState = Mockery::mock(\OguzhanTogay\HueClient\Resources\GroupState::class);

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
        $response = new Response();

        $result = $this->controller->getAll($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertIsArray($body);
        $this->assertCount(1, $body);
        $this->assertEquals('Living Room', $body[0]['name']);
    }

    public function testGetRooms(): void
    {
        $mockGroups = Mockery::mock(\OguzhanTogay\HueClient\Resources\Groups::class);
        $mockGroup = Mockery::mock(\OguzhanTogay\HueClient\Resources\Group::class);
        $mockState = Mockery::mock(\OguzhanTogay\HueClient\Resources\GroupState::class);

        $this->mockClient->shouldReceive('groups')->andReturn($mockGroups);
        $mockGroups->shouldReceive('getRooms')->andReturn([$mockGroup]);
        
        $mockGroup->shouldReceive('getId')->andReturn(1);
        $mockGroup->shouldReceive('getName')->andReturn('Bedroom');
        $mockGroup->shouldReceive('getType')->andReturn('Room');
        $mockGroup->shouldReceive('getClass')->andReturn('Bedroom');
        $mockGroup->shouldReceive('getLights')->andReturn([1, 2]);
        $mockGroup->shouldReceive('getState')->andReturn($mockState);
        
        $mockState->shouldReceive('toArray')->andReturn([
            'all_on' => false,
            'any_on' => true
        ]);

        $request = $this->createRequest('GET', '/api/rooms');
        $response = new Response();

        $result = $this->controller->getRooms($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertIsArray($body);
        $this->assertCount(1, $body);
        $this->assertEquals('Bedroom', $body[0]['name']);
    }

    public function testSetGroupAction(): void
    {
        $mockGroups = Mockery::mock(\OguzhanTogay\HueClient\Resources\Groups::class);
        $mockGroup = Mockery::mock(\OguzhanTogay\HueClient\Resources\Group::class);

        $this->mockClient->shouldReceive('groups')->andReturn($mockGroups);
        $mockGroups->shouldReceive('get')->with(1)->andReturn($mockGroup);
        
        $mockGroup->shouldReceive('on')->once();
        $mockGroup->shouldReceive('setBrightness')->with(75)->once();
        $mockGroup->shouldReceive('setColor')->with('#FF5733')->once();

        $payload = json_encode([
            'on' => true,
            'brightness' => 75,
            'color' => '#FF5733'
        ]);

        $request = $this->createRequest('PUT', '/api/groups/1/action', [], $payload);
        $response = new Response();

        $result = $this->controller->setAction($request, $response, ['id' => '1']);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['success']);
    }

    public function testCreateGroup(): void
    {
        $mockGroups = Mockery::mock(\OguzhanTogay\HueClient\Resources\Groups::class);
        $mockGroup = Mockery::mock(\OguzhanTogay\HueClient\Resources\Group::class);

        $this->mockClient->shouldReceive('groups')->andReturn($mockGroups);
        $mockGroups->shouldReceive('create')
            ->with('New Group', [1, 2, 3], 'LightGroup')
            ->andReturn($mockGroup);
        
        $mockGroup->shouldReceive('getId')->andReturn(5);
        $mockGroup->shouldReceive('getName')->andReturn('New Group');

        $payload = json_encode([
            'name' => 'New Group',
            'lights' => [1, 2, 3],
            'type' => 'LightGroup'
        ]);

        $request = $this->createRequest('POST', '/api/groups', [], $payload);
        $response = new Response();

        $result = $this->controller->create($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals('New Group', $body['data']['name']);
    }

    public function testInvalidJsonPayload(): void
    {
        $request = $this->createRequest('PUT', '/api/groups/1/action', [], 'invalid json');
        $response = new Response();

        $result = $this->controller->setAction($request, $response, ['id' => '1']);

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