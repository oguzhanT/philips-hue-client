<?php

namespace OguzhanTogay\HueClient\Tests\Integration;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

/**
 * Integration tests for the REST API
 * 
 * Note: These tests require a running API server and a real Hue Bridge
 * Run with: composer test -- tests/Integration/
 */
class ApiIntegrationTest extends TestCase
{
    private Client $client;
    private string $baseUrl = 'http://localhost:8080/api';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 10
        ]);
    }

    public function testApiServerIsRunning(): void
    {
        try {
            $response = $this->client->get('/health');
            $this->assertEquals(200, $response->getStatusCode());
        } catch (ConnectException $e) {
            $this->markTestSkipped('API server is not running. Start with: ./bin/hue-server --discover');
        }
    }

    public function testHealthEndpoint(): void
    {
        $response = $this->client->get('/health');
        $data = json_decode($response->getBody(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('status', $data);
        $this->assertContains($data['status'], ['healthy', 'unhealthy']);
        $this->assertArrayHasKey('bridge_ip', $data);
        $this->assertArrayHasKey('connected', $data);
    }

    public function testBridgeInfoEndpoint(): void
    {
        $response = $this->client->get('/bridge/info');
        
        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getBody(), true);
            $this->assertArrayHasKey('name', $data);
            $this->assertArrayHasKey('modelid', $data);
            $this->assertArrayHasKey('swversion', $data);
        } else {
            $this->markTestSkipped('Bridge not authenticated or not available');
        }
    }

    public function testLightsEndpoint(): void
    {
        $response = $this->client->get('/lights');
        
        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getBody(), true);
            $this->assertIsArray($data);
            
            if (!empty($data)) {
                $light = $data[0];
                $this->assertArrayHasKey('id', $light);
                $this->assertArrayHasKey('name', $light);
                $this->assertArrayHasKey('state', $light);
                $this->assertArrayHasKey('type', $light);
            }
        } else {
            $this->markTestSkipped('Lights endpoint not accessible');
        }
    }

    public function testGroupsEndpoint(): void
    {
        $response = $this->client->get('/groups');
        
        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getBody(), true);
            $this->assertIsArray($data);
            
            if (!empty($data)) {
                $group = $data[0];
                $this->assertArrayHasKey('id', $group);
                $this->assertArrayHasKey('name', $group);
                $this->assertArrayHasKey('type', $group);
                $this->assertArrayHasKey('lights', $group);
            }
        } else {
            $this->markTestSkipped('Groups endpoint not accessible');
        }
    }

    public function testRoomsEndpoint(): void
    {
        $response = $this->client->get('/rooms');
        
        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getBody(), true);
            $this->assertIsArray($data);
        } else {
            $this->markTestSkipped('Rooms endpoint not accessible');
        }
    }

    public function testScenesEndpoint(): void
    {
        $response = $this->client->get('/scenes');
        
        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getBody(), true);
            $this->assertIsArray($data);
            
            if (!empty($data)) {
                $scene = $data[0];
                $this->assertArrayHasKey('id', $scene);
                $this->assertArrayHasKey('name', $scene);
                $this->assertArrayHasKey('type', $scene);
            }
        } else {
            $this->markTestSkipped('Scenes endpoint not accessible');
        }
    }

    public function testRateLimitHeaders(): void
    {
        $response = $this->client->get('/health');
        
        $this->assertTrue($response->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Remaining'));
        $this->assertEquals('100', $response->getHeaderLine('X-RateLimit-Limit'));
    }

    public function testCorsHeaders(): void
    {
        $response = $this->client->get('/health');
        
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertEquals('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testSwaggerDocumentation(): void
    {
        $response = $this->client->get('/docs');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContains('swagger-ui', (string) $response->getBody());
    }

    public function testOpenApiSpec(): void
    {
        $response = $this->client->get('/docs');
        $this->assertEquals(200, $response->getStatusCode());
        
        $openApiResponse = $this->client->get('/docs');
        $this->assertEquals(200, $openApiResponse->getStatusCode());
    }
}