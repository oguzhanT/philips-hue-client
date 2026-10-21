<?php

namespace OguzhanTogay\HueClient\Tests\Color;

use OguzhanTogay\HueClient\Color\Color;
use OguzhanTogay\HueClient\Color\Gamut;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    public function testHexParsing(): void
    {
        $this->assertSame([255, 87, 51], Color::fromHex('#FF5733')->toRgb());
        $this->assertSame([255, 255, 255], Color::fromHex('fff')->toRgb());
    }

    public function testRejectsBadHex(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Color::fromHex('#GG0000');
    }

    public function testWhiteIsNearTheD65Point(): void
    {
        [$x, $y] = Color::fromHex('#FFFFFF')->toXy();

        $this->assertEqualsWithDelta(0.3227, $x, 0.01);
        $this->assertEqualsWithDelta(0.329, $y, 0.01);
    }

    public function testBlackFallsBackToWhitePoint(): void
    {
        $this->assertSame([0.3127, 0.329], Color::fromHex('#000000')->toXy());
    }

    public function testPointsInsideTheGamutAreKept(): void
    {
        $gamut = Gamut::byType('C');

        $this->assertSame([0.3, 0.3], $gamut->clamp([0.3, 0.3]));
    }

    public function testPointsOutsideMoveOntoTheTriangle(): void
    {
        $gamut = Gamut::byType('B');
        $clamped = $gamut->clamp([0.1, 0.8]);

        $this->assertNotSame([0.1, 0.8], $clamped);
        $this->assertTrue($gamut->contains([round($clamped[0], 6), round($clamped[1], 6)]) || $this->onEdge($gamut, $clamped));
    }

    private function onEdge(Gamut $gamut, array $p): bool
    {
        // Allow for floating point: a point on the edge may test as just outside.
        $nudged = [($p[0] * 0.999) + (0.3 * 0.001), ($p[1] * 0.999) + (0.3 * 0.001)];

        return $gamut->contains($nudged);
    }
}
