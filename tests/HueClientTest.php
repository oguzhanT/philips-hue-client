<?php

namespace OguzhanTogay\HueClient\Tests;

use PHPUnit\Framework\TestCase;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Resources\Lights;
use OguzhanTogay\HueClient\Resources\Groups;
use OguzhanTogay\HueClient\Resources\Scenes;

class HueClientTest extends TestCase
{
    private HueClient $client;

    protected function setUp(): void
    {
        $this->client = new HueClient('192.168.1.100', 'test-username');
    }

    public function testClientInitialization(): void
    {
        $this->assertEquals('192.168.1.100', $this->client->getBridgeIp());
        $this->assertEquals('test-username', $this->client->getUsername());
    }

    public function testLightsResourceCreation(): void
    {
        $lights = $this->client->lights();
        $this->assertInstanceOf(Lights::class, $lights);
        
        // Test singleton behavior
        $this->assertSame($lights, $this->client->lights());
    }

    public function testGroupsResourceCreation(): void
    {
        $groups = $this->client->groups();
        $this->assertInstanceOf(Groups::class, $groups);
        
        // Test singleton behavior
        $this->assertSame($groups, $this->client->groups());
    }

    public function testScenesResourceCreation(): void
    {
        $scenes = $this->client->scenes();
        $this->assertInstanceOf(Scenes::class, $scenes);
        
        // Test singleton behavior
        $this->assertSame($scenes, $this->client->scenes());
    }

    public function testSetUsername(): void
    {
        $newUsername = 'new-test-username';
        $this->client->setUsername($newUsername);
        $this->assertEquals($newUsername, $this->client->getUsername());
    }
}