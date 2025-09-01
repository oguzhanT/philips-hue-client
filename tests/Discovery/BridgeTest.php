<?php

namespace OguzhanTogay\HueClient\Tests\Discovery;

use PHPUnit\Framework\TestCase;
use OguzhanTogay\HueClient\Discovery\Bridge;

class BridgeTest extends TestCase
{
    private Bridge $bridge;

    protected function setUp(): void
    {
        $this->bridge = new Bridge(
            '001788fffe20336d',
            '192.168.1.100',
            443,
            'Philips hue',
            'BSB002',
            '1.54.0'
        );
    }

    public function testBridgeCreation(): void
    {
        $this->assertEquals('001788fffe20336d', $this->bridge->getId());
        $this->assertEquals('192.168.1.100', $this->bridge->getIp());
        $this->assertEquals(443, $this->bridge->getPort());
        $this->assertEquals('Philips hue', $this->bridge->getName());
        $this->assertEquals('BSB002', $this->bridge->getModelId());
        $this->assertEquals('1.54.0', $this->bridge->getSwVersion());
    }

    public function testGetBaseUrl(): void
    {
        $expectedUrl = 'https://192.168.1.100:443';
        $this->assertEquals($expectedUrl, $this->bridge->getBaseUrl());
    }

    public function testToArray(): void
    {
        $expected = [
            'id' => '001788fffe20336d',
            'ip' => '192.168.1.100',
            'port' => 443,
            'name' => 'Philips hue',
            'model_id' => 'BSB002',
            'sw_version' => '1.54.0',
        ];

        $this->assertEquals($expected, $this->bridge->toArray());
    }

    public function testBridgeWithMinimalData(): void
    {
        $bridge = new Bridge('test-id', '192.168.1.101');
        
        $this->assertEquals('test-id', $bridge->getId());
        $this->assertEquals('192.168.1.101', $bridge->getIp());
        $this->assertEquals(443, $bridge->getPort()); // Default port
        $this->assertNull($bridge->getName());
        $this->assertNull($bridge->getModelId());
        $this->assertNull($bridge->getSwVersion());
    }
}