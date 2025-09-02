<?php

namespace OguzhanTogay\HueClient\Tests\Api\Controllers;

use PHPUnit\Framework\TestCase;
use Mockery;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Api\Controllers\SchedulesController;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;

class SchedulesControllerTest extends TestCase
{
    private HueClient $mockClient;
    private SchedulesController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = Mockery::mock(HueClient::class);
        $this->controller = new SchedulesController($this->mockClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetAllSchedules(): void
    {
        $mockSchedules = Mockery::mock(\OguzhanTogay\HueClient\Resources\Schedules::class);
        $mockSchedule = Mockery::mock(\OguzhanTogay\HueClient\Resources\Schedule::class);

        $this->mockClient->shouldReceive('schedules')->andReturn($mockSchedules);
        $mockSchedules->shouldReceive('getAll')->andReturn([$mockSchedule]);
        
        $mockSchedule->shouldReceive('getId')->andReturn(1);
        $mockSchedule->shouldReceive('getName')->andReturn('Morning Routine');
        $mockSchedule->shouldReceive('getDescription')->andReturn('Turn lights on');
        $mockSchedule->shouldReceive('getCommand')->andReturn(['address' => '/api/groups/1/action']);
        $mockSchedule->shouldReceive('getLocalTime')->andReturn('W127/T07:00:00');
        $mockSchedule->shouldReceive('getCreated')->andReturn('2023-01-01T00:00:00');
        $mockSchedule->shouldReceive('getStatus')->andReturn('enabled');

        $request = $this->createRequest('GET', '/api/schedules');
        $response = new Response();

        $result = $this->controller->getAll($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertIsArray($body);
        $this->assertCount(1, $body);
        $this->assertEquals('Morning Routine', $body[0]['name']);
    }

    public function testGetSpecificSchedule(): void
    {
        $mockSchedules = Mockery::mock(\OguzhanTogay\HueClient\Resources\Schedules::class);
        $mockSchedule = Mockery::mock(\OguzhanTogay\HueClient\Resources\Schedule::class);

        $this->mockClient->shouldReceive('schedules')->andReturn($mockSchedules);
        $mockSchedules->shouldReceive('get')->with(1)->andReturn($mockSchedule);
        
        $mockSchedule->shouldReceive('getId')->andReturn(1);
        $mockSchedule->shouldReceive('getName')->andReturn('Evening Routine');
        $mockSchedule->shouldReceive('getDescription')->andReturn('Turn lights off');
        $mockSchedule->shouldReceive('getCommand')->andReturn(['address' => '/api/groups/1/action']);
        $mockSchedule->shouldReceive('getLocalTime')->andReturn('W127/T22:00:00');
        $mockSchedule->shouldReceive('getCreated')->andReturn('2023-01-01T12:00:00');
        $mockSchedule->shouldReceive('getStatus')->andReturn('enabled');

        $request = $this->createRequest('GET', '/api/schedules/1');
        $response = new Response();

        $result = $this->controller->get($request, $response, ['id' => '1']);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertEquals('Evening Routine', $body['name']);
        $this->assertEquals(1, $body['id']);
    }

    public function testCreateSchedule(): void
    {
        $mockSchedules = Mockery::mock(\OguzhanTogay\HueClient\Resources\Schedules::class);
        $mockSchedule = Mockery::mock(\OguzhanTogay\HueClient\Resources\Schedule::class);

        $this->mockClient->shouldReceive('schedules')->andReturn($mockSchedules);
        $mockSchedules->shouldReceive('create')
            ->with('New Schedule', ['address' => '/api/groups/1/action'], 'W127/T08:00:00')
            ->andReturn($mockSchedule);
        
        $mockSchedule->shouldReceive('getId')->andReturn(5);
        $mockSchedule->shouldReceive('getName')->andReturn('New Schedule');

        $payload = json_encode([
            'name' => 'New Schedule',
            'command' => ['address' => '/api/groups/1/action'],
            'localtime' => 'W127/T08:00:00'
        ]);

        $request = $this->createRequest('POST', '/api/schedules', [], $payload);
        $response = new Response();

        $result = $this->controller->create($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertEquals('New Schedule', $body['data']['name']);
    }

    public function testUpdateSchedule(): void
    {
        $mockSchedules = Mockery::mock(\OguzhanTogay\HueClient\Resources\Schedules::class);
        $mockSchedule = Mockery::mock(\OguzhanTogay\HueClient\Resources\Schedule::class);

        $this->mockClient->shouldReceive('schedules')->andReturn($mockSchedules);
        $mockSchedules->shouldReceive('get')->with(1)->andReturn($mockSchedule);
        $mockSchedule->shouldReceive('modify')->with(['status' => 'disabled'])->once();

        $payload = json_encode([
            'status' => 'disabled'
        ]);

        $request = $this->createRequest('PUT', '/api/schedules/1', [], $payload);
        $response = new Response();

        $result = $this->controller->update($request, $response, ['id' => '1']);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertTrue($body['success']);
    }

    public function testDeleteSchedule(): void
    {
        $mockSchedules = Mockery::mock(\OguzhanTogay\HueClient\Resources\Schedules::class);

        $this->mockClient->shouldReceive('schedules')->andReturn($mockSchedules);
        $mockSchedules->shouldReceive('delete')->with(1)->once();

        $request = $this->createRequest('DELETE', '/api/schedules/1');
        $response = new Response();

        $result = $this->controller->delete($request, $response, ['id' => '1']);

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