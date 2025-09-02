<?php

namespace OguzhanTogay\HueClient\Effects;

class Breathing extends BaseEffect
{
    public function start($target, string $color = '#FFFFFF', string $speed = 'medium', int $duration = 30): void
    {
        $this->running = true;

        $speedMap = [
            'slow' => 3000,
            'medium' => 2000,
            'fast' => 1000
        ];

        $cycleTime = $speedMap[$speed] ?? 2000;
        $halfCycle = $cycleTime / 2;

        $rgb = $this->hexToRgb($color);
        $xy = $this->rgbToXy($rgb);

        $endTime = time() + $duration;

        while ($this->running && time() < $endTime) {
            // Fade to full brightness
            $this->setLightState($target, [
                'on' => true,
                'bri' => 254,
                'xy' => $xy,
                'transitiontime' => (int) round($halfCycle / 100)
            ]);

            $this->sleep($halfCycle);

            if (!$this->running) {
                break;
            }

            // Fade to dim
            $this->setLightState($target, [
                'bri' => 10,
                'transitiontime' => (int) round($halfCycle / 100)
            ]);

            $this->sleep($halfCycle);
        }

        $this->running = false;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }

    private function rgbToXy(array $rgb): array
    {
        // Normalize RGB values
        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;

        // Apply gamma correction
        $r = ($r > 0.04045) ? pow(($r + 0.055) / 1.055, 2.4) : ($r / 12.92);
        $g = ($g > 0.04045) ? pow(($g + 0.055) / 1.055, 2.4) : ($g / 12.92);
        $b = ($b > 0.04045) ? pow(($b + 0.055) / 1.055, 2.4) : ($b / 12.92);

        // Convert to XYZ
        $X = $r * 0.4124564 + $g * 0.3575761 + $b * 0.1804375;
        $Y = $r * 0.2126729 + $g * 0.7151522 + $b * 0.0721750;
        $Z = $r * 0.0193339 + $g * 0.1191920 + $b * 0.9503041;

        // Calculate xy
        $sum = $X + $Y + $Z;
        if ($sum == 0) {
            return [0.3127, 0.3290]; // Default white
        }

        $x = $X / $sum;
        $y = $Y / $sum;

        return [$x, $y];
    }
}
