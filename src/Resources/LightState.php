<?php

namespace OguzhanTogay\HueClient\Resources;

class LightState
{
    private array $state;

    public function __construct(array $state = [])
    {
        $this->state = $state;
    }

    public function isOn(): bool
    {
        return $this->state['on'] ?? false;
    }

    public function getBrightness(): int
    {
        $bri = $this->state['bri'] ?? 0;
        return (int) round(($bri / 254) * 100);
    }

    public function getHue(): ?int
    {
        return $this->state['hue'] ?? null;
    }

    public function getSaturation(): ?int
    {
        $sat = $this->state['sat'] ?? null;
        return $sat !== null ? (int) round(($sat / 254) * 100) : null;
    }

    public function getColorTemperature(): ?int
    {
        $ct = $this->state['ct'] ?? null;
        return $ct !== null ? (int) round(1000000 / $ct) : null;
    }

    public function getXy(): ?array
    {
        return $this->state['xy'] ?? null;
    }

    public function getColorMode(): ?string
    {
        return $this->state['colormode'] ?? null;
    }

    public function getAlert(): ?string
    {
        return $this->state['alert'] ?? null;
    }

    public function getEffect(): ?string
    {
        return $this->state['effect'] ?? null;
    }

    public function isReachable(): bool
    {
        return $this->state['reachable'] ?? false;
    }

    public function getStatus(): string
    {
        if (!$this->isReachable()) {
            return 'Unreachable';
        }
        
        return $this->isOn() ? 'On' : 'Off';
    }

    public function set(string $key, $value): void
    {
        $this->state[$key] = $value;
    }

    public function toArray(): array
    {
        return $this->state;
    }
}