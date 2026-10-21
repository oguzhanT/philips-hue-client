<?php

namespace OguzhanTogay\HueClient\Color;

/**
 * The color triangle a Hue lamp can reproduce. v2 lights report theirs as
 * color.gamut_type (A, B or C) and color.gamut.
 */
final class Gamut
{
    /**
     * @param array{float, float} $red
     * @param array{float, float} $green
     * @param array{float, float} $blue
     */
    public function __construct(
        public readonly array $red,
        public readonly array $green,
        public readonly array $blue
    ) {
    }

    public static function byType(string $type): self
    {
        return match (strtoupper($type)) {
            'A' => new self([0.704, 0.296], [0.2151, 0.7106], [0.138, 0.08]),
            'B' => new self([0.675, 0.322], [0.409, 0.518], [0.167, 0.04]),
            'C' => new self([0.6915, 0.3083], [0.17, 0.7], [0.1532, 0.0475]),
            default => throw new \InvalidArgumentException("Unknown gamut type '{$type}'."),
        };
    }

    /**
     * From a v2 light's color.gamut object.
     */
    public static function fromV2(array $gamut): self
    {
        $point = static fn (array $p): array => [(float) $p['x'], (float) $p['y']];

        return new self($point($gamut['red']), $point($gamut['green']), $point($gamut['blue']));
    }

    /**
     * @param array{float, float} $point
     */
    public function contains(array $point): bool
    {
        $sign = static fn (array $p, array $a, array $b): float =>
            ($p[0] - $b[0]) * ($a[1] - $b[1]) - ($a[0] - $b[0]) * ($p[1] - $b[1]);

        $d1 = $sign($point, $this->red, $this->green);
        $d2 = $sign($point, $this->green, $this->blue);
        $d3 = $sign($point, $this->blue, $this->red);

        $negative = $d1 < 0 || $d2 < 0 || $d3 < 0;
        $positive = $d1 > 0 || $d2 > 0 || $d3 > 0;

        return !($negative && $positive);
    }

    /**
     * The point itself when the lamp can show it, else the nearest point on
     * the triangle's edge.
     *
     * @param array{float, float} $point
     * @return array{float, float}
     */
    public function clamp(array $point): array
    {
        if ($this->contains($point)) {
            return $point;
        }

        $best = null;
        $bestDistance = INF;
        foreach ([[$this->red, $this->green], [$this->green, $this->blue], [$this->blue, $this->red]] as [$a, $b]) {
            $candidate = self::closestOnSegment($point, $a, $b);
            $distance = ($candidate[0] - $point[0]) ** 2 + ($candidate[1] - $point[1]) ** 2;
            if ($distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    private static function closestOnSegment(array $p, array $a, array $b): array
    {
        $ab = [$b[0] - $a[0], $b[1] - $a[1]];
        $length = $ab[0] ** 2 + $ab[1] ** 2;
        $t = $length == 0.0 ? 0.0 : max(0.0, min(1.0, (($p[0] - $a[0]) * $ab[0] + ($p[1] - $a[1]) * $ab[1]) / $length));

        return [$a[0] + $t * $ab[0], $a[1] + $t * $ab[1]];
    }
}
