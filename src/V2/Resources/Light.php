<?php

namespace OguzhanTogay\HueClient\V2\Resources;

use OguzhanTogay\HueClient\Color\Color;
use OguzhanTogay\HueClient\Color\Gamut;

/**
 * A v2 light. Every setter sends one request; pass $transitionMs for a fade.
 *
 *   $light->on()->color('#FF5733')->brightness(60);
 *   $light->colorTemperature(2700, transitionMs: 2000);
 *   $light->effect('candle');
 */
class Light extends Resource
{
    public function isOn(): bool
    {
        return (bool) ($this->data['on']['on'] ?? false);
    }

    /** Brightness in percent (0-100), or null for a lamp that cannot dim. */
    public function brightness(): ?float
    {
        return isset($this->data['dimming']['brightness']) ? (float) $this->data['dimming']['brightness'] : null;
    }

    public function supportsColor(): bool
    {
        return isset($this->data['color']);
    }

    /** @return string[] effects this lamp offers, e.g. candle, fire, sparkle */
    public function effects(): array
    {
        return $this->data['effects']['effect_values'] ?? [];
    }

    public function on(?int $transitionMs = null): static
    {
        return $this->set(['on' => ['on' => true]], $transitionMs);
    }

    public function off(?int $transitionMs = null): static
    {
        return $this->set(['on' => ['on' => false]], $transitionMs);
    }

    public function toggle(): static
    {
        return $this->isOn() ? $this->off() : $this->on();
    }

    /**
     * @param float $percent 0-100; 0 is the lamp's lowest level, not off
     */
    public function setBrightness(float $percent, ?int $transitionMs = null): static
    {
        return $this->set(['dimming' => ['brightness' => max(0.0, min(100.0, $percent))]], $transitionMs);
    }

    /**
     * A hex color, moved into what this lamp can show.
     */
    public function color(string $hex, ?int $transitionMs = null): static
    {
        if (!$this->supportsColor()) {
            throw new \LogicException("Light '{$this->name()}' has no color.");
        }

        $gamut = isset($this->data['color']['gamut']) ? Gamut::fromV2($this->data['color']['gamut']) : null;
        [$x, $y] = Color::fromHex($hex)->toXy($gamut);

        return $this->set(['on' => ['on' => true], 'color' => ['xy' => ['x' => $x, 'y' => $y]]], $transitionMs);
    }

    /**
     * White in Kelvin, clamped to the lamp's range.
     */
    public function colorTemperature(int $kelvin, ?int $transitionMs = null): static
    {
        if (!isset($this->data['color_temperature'])) {
            throw new \LogicException("Light '{$this->name()}' has no color temperature.");
        }

        $schema = $this->data['color_temperature']['mirek_schema'] ?? ['mirek_minimum' => 153, 'mirek_maximum' => 500];
        $mirek = (int) round(1_000_000 / max(1, $kelvin));
        $mirek = max((int) $schema['mirek_minimum'], min((int) $schema['mirek_maximum'], $mirek));

        return $this->set(['on' => ['on' => true], 'color_temperature' => ['mirek' => $mirek]], $transitionMs);
    }

    /**
     * One of effects(), or 'no_effect' to stop.
     */
    public function effect(string $effect): static
    {
        $offered = $this->effects();
        if ($offered !== [] && !in_array($effect, $offered, true)) {
            throw new \InvalidArgumentException(
                "Light '{$this->name()}' offers these effects: " . implode(', ', $offered)
            );
        }

        return $this->update(['effects' => ['effect' => $effect]]);
    }

    /**
     * Breathes once so you can tell which lamp this is.
     */
    public function identify(): static
    {
        $this->client->request('PUT', "resource/light/{$this->id()}", ['identify' => ['action' => 'identify']]);

        return $this;
    }

    private function set(array $changes, ?int $transitionMs): static
    {
        if ($transitionMs !== null) {
            $changes['dynamics'] = ['duration' => max(0, $transitionMs)];
        }

        return $this->update($changes);
    }
}
