<?php

namespace OguzhanTogay\HueClient\V2\Resources;

use OguzhanTogay\HueClient\Color\Color;

/**
 * All the lights of a room or zone, switched together in one request.
 */
class GroupedLight extends Resource
{
    public function isOn(): bool
    {
        return (bool) ($this->data['on']['on'] ?? false);
    }

    public function on(?int $transitionMs = null): static
    {
        return $this->set(['on' => ['on' => true]], $transitionMs);
    }

    public function off(?int $transitionMs = null): static
    {
        return $this->set(['on' => ['on' => false]], $transitionMs);
    }

    public function setBrightness(float $percent, ?int $transitionMs = null): static
    {
        return $this->set(['dimming' => ['brightness' => max(0.0, min(100.0, $percent))]], $transitionMs);
    }

    /**
     * Each lamp shows the nearest color it can.
     */
    public function color(string $hex, ?int $transitionMs = null): static
    {
        [$x, $y] = Color::fromHex($hex)->toXy();

        return $this->set(['on' => ['on' => true], 'color' => ['xy' => ['x' => $x, 'y' => $y]]], $transitionMs);
    }

    public function colorTemperature(int $kelvin, ?int $transitionMs = null): static
    {
        $mirek = max(153, min(500, (int) round(1_000_000 / max(1, $kelvin))));

        return $this->set(['on' => ['on' => true], 'color_temperature' => ['mirek' => $mirek]], $transitionMs);
    }

    private function set(array $changes, ?int $transitionMs): static
    {
        if ($transitionMs !== null) {
            $changes['dynamics'] = ['duration' => max(0, $transitionMs)];
        }

        return $this->update($changes);
    }
}
