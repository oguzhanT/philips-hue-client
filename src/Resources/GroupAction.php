<?php

namespace OguzhanTogay\HueClient\Resources;

class GroupAction
{
    private array $action;

    public function __construct(array $action = [])
    {
        $this->action = $action;
    }

    public function isOn(): bool
    {
        return $this->action['on'] ?? false;
    }

    public function getBrightness(): int
    {
        $bri = $this->action['bri'] ?? 0;
        return (int) round(($bri / 254) * 100);
    }

    public function getHue(): ?int
    {
        return $this->action['hue'] ?? null;
    }

    public function getSaturation(): ?int
    {
        $sat = $this->action['sat'] ?? null;
        return $sat !== null ? (int) round(($sat / 254) * 100) : null;
    }

    public function getColorTemperature(): ?int
    {
        $ct = $this->action['ct'] ?? null;
        return $ct !== null ? (int) round(1000000 / $ct) : null;
    }

    public function getXy(): ?array
    {
        return $this->action['xy'] ?? null;
    }

    public function getColorMode(): ?string
    {
        return $this->action['colormode'] ?? null;
    }

    public function getAlert(): ?string
    {
        return $this->action['alert'] ?? null;
    }

    public function getEffect(): ?string
    {
        return $this->action['effect'] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->action[$key] = $value;
    }

    public function toArray(): array
    {
        return $this->action;
    }
}
