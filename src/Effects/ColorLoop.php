<?php

namespace OguzhanTogay\HueClient\Effects;

use OguzhanTogay\HueClient\Color\Color;
use OguzhanTogay\HueClient\Resources\Light;
use OguzhanTogay\HueClient\Resources\Group;

class ColorLoop extends BaseEffect
{
    public function start($target, int $duration = 30, int $speed = 1000): void
    {
        $this->running = true;

        // Enable color loop effect
        $this->setLightState($target, [
            'on' => true,
            'effect' => 'colorloop'
        ]);

        // Run for specified duration
        $this->sleep($duration * 1000);

        // Disable effect
        if ($this->running) {
            $this->setLightState($target, [
                'effect' => 'none'
            ]);
        }

        $this->running = false;
    }

    public function startCustom($target, array $colors, int $stepDuration = 1000, int $cycles = 1): void
    {
        $this->running = true;

        for ($cycle = 0; $cycle < $cycles && $this->running; $cycle++) {
            foreach ($colors as $color) {
                if (!$this->running) {
                    break;
                }

                $xy = Color::fromHex($color)->toXy();

                $this->setLightState($target, [
                    'on' => true,
                    'xy' => $xy,
                    'transitiontime' => (int) round($stepDuration / 100)
                ]);

                $this->sleep($stepDuration);
            }
        }

        $this->running = false;
    }
}
