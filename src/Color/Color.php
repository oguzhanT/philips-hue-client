<?php

namespace OguzhanTogay\HueClient\Color;

/**
 * An sRGB color and its CIE xy coordinates, as Hue lights take them.
 *
 *   Color::fromHex('#FF5733')->toXy();                 // [x, y]
 *   Color::fromHex('#FF5733')->toXy(Gamut::byType('C')); // clamped to what the lamp can show
 */
final class Color
{
    private function __construct(
        public readonly int $red,
        public readonly int $green,
        public readonly int $blue
    ) {
    }

    public static function fromHex(string $hex): self
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            throw new \InvalidArgumentException("Not a hex color: '#{$hex}'");
        }

        [$red, $green, $blue] = array_map(static fn (string $pair): int => (int) hexdec($pair), str_split($hex, 2));

        return new self($red, $green, $blue);
    }

    public static function fromRgb(int $red, int $green, int $blue): self
    {
        foreach ([$red, $green, $blue] as $channel) {
            if ($channel < 0 || $channel > 255) {
                throw new \InvalidArgumentException('RGB channels must be 0-255.');
            }
        }

        return new self($red, $green, $blue);
    }

    /**
     * @return array{int, int, int}
     */
    public function toRgb(): array
    {
        return [$this->red, $this->green, $this->blue];
    }

    /**
     * CIE xy using the wide-gamut conversion Philips documents, rounded to
     * four decimals and, given a gamut, moved to the nearest color it can show.
     *
     * @return array{float, float}
     */
    public function toXy(?Gamut $gamut = null): array
    {
        $linear = static function (int $channel): float {
            $c = $channel / 255;
            return $c > 0.04045 ? (($c + 0.055) / 1.055) ** 2.4 : $c / 12.92;
        };

        $r = $linear($this->red);
        $g = $linear($this->green);
        $b = $linear($this->blue);

        $x = $r * 0.664511 + $g * 0.154324 + $b * 0.162028;
        $y = $r * 0.283881 + $g * 0.668433 + $b * 0.047685;
        $z = $r * 0.000088 + $g * 0.072310 + $b * 0.986039;
        $sum = $x + $y + $z;

        // Black has no chromaticity; use the white point.
        $point = $sum == 0.0 ? [0.3127, 0.3290] : [$x / $sum, $y / $sum];

        if ($gamut !== null) {
            $point = $gamut->clamp($point);
        }

        return [round($point[0], 4), round($point[1], 4)];
    }
}
