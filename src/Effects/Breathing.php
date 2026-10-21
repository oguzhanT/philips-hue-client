<?php

namespace OguzhanTogay\HueClient\Effects;

use OguzhanTogay\HueClient\Color\Color;

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

        $xy = Color::fromHex($color)->toXy();

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
}
