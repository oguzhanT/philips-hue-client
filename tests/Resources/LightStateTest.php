<?php

namespace OguzhanTogay\HueClient\Tests\Resources;

use PHPUnit\Framework\TestCase;
use OguzhanTogay\HueClient\Resources\LightState;

class LightStateTest extends TestCase
{
    private LightState $lightState;

    protected function setUp(): void
    {
        $stateData = [
            'on' => true,
            'bri' => 200,
            'hue' => 15331,
            'sat' => 121,
            'ct' => 369,
            'xy' => [0.4448, 0.4066],
            'colormode' => 'xy',
            'alert' => 'none',
            'effect' => 'none',
            'reachable' => true
        ];

        $this->lightState = new LightState($stateData);
    }

    public function testIsOn(): void
    {
        $this->assertTrue($this->lightState->isOn());
    }

    public function testGetBrightness(): void
    {
        // bri 200 should convert to ~78%
        $brightness = $this->lightState->getBrightness();
        $this->assertEquals(79, $brightness); // 200/254 * 100 ≈ 79
    }

    public function testGetHue(): void
    {
        $this->assertEquals(15331, $this->lightState->getHue());
    }

    public function testGetSaturation(): void
    {
        // sat 121 should convert to ~48%
        $saturation = $this->lightState->getSaturation();
        $this->assertEquals(48, $saturation); // 121/254 * 100 ≈ 48
    }

    public function testGetColorTemperature(): void
    {
        // ct 369 should convert to ~2710K
        $temperature = $this->lightState->getColorTemperature();
        $this->assertEquals(2710, $temperature); // 1000000/369 ≈ 2710
    }

    public function testGetXy(): void
    {
        $expectedXy = [0.4448, 0.4066];
        $this->assertEquals($expectedXy, $this->lightState->getXy());
    }

    public function testGetColorMode(): void
    {
        $this->assertEquals('xy', $this->lightState->getColorMode());
    }

    public function testIsReachable(): void
    {
        $this->assertTrue($this->lightState->isReachable());
    }

    public function testGetStatus(): void
    {
        $this->assertEquals('On', $this->lightState->getStatus());

        // Test unreachable light
        $unreachableState = new LightState(['on' => true, 'reachable' => false]);
        $this->assertEquals('Unreachable', $unreachableState->getStatus());

        // Test off light
        $offState = new LightState(['on' => false, 'reachable' => true]);
        $this->assertEquals('Off', $offState->getStatus());
    }

    public function testSetAndToArray(): void
    {
        $this->lightState->set('test_key', 'test_value');
        $state = $this->lightState->toArray();
        
        $this->assertEquals('test_value', $state['test_key']);
    }

    public function testEmptyState(): void
    {
        $emptyState = new LightState();
        
        $this->assertFalse($emptyState->isOn());
        $this->assertEquals(0, $emptyState->getBrightness());
        $this->assertNull($emptyState->getHue());
        $this->assertFalse($emptyState->isReachable());
        $this->assertEquals('Unreachable', $emptyState->getStatus());
    }
}