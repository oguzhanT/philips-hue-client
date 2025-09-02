<?php

namespace OguzhanTogay\HueClient\Resources;

use OguzhanTogay\HueClient\HueClient;

class Light
{
    private HueClient $client;
    private int $id;
    private array $attributes;
    private LightState $state;

    public function __construct(HueClient $client, int $id, array $attributes)
    {
        $this->client = $client;
        $this->id = $id;
        $this->attributes = $attributes;
        $this->state = new LightState($attributes['state'] ?? []);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->attributes['name'] ?? '';
    }

    public function getType(): string
    {
        return $this->attributes['type'] ?? '';
    }

    public function getModelId(): string
    {
        return $this->attributes['modelid'] ?? '';
    }

    public function getManufacturer(): string
    {
        return $this->attributes['manufacturername'] ?? '';
    }

    public function getState(): LightState
    {
        return $this->state;
    }

    public function refresh(): self
    {
        $data = $this->client->request('GET', "lights/{$this->id}");
        $this->attributes = $data;
        $this->state = new LightState($data['state'] ?? []);
        return $this;
    }

    public function on(): self
    {
        return $this->setState(['on' => true]);
    }

    public function off(): self
    {
        return $this->setState(['on' => false]);
    }

    public function toggle(): self
    {
        return $this->setState(['on' => !$this->state->isOn()]);
    }

    public function setBrightness(int $brightness): self
    {
        $bri = (int) round(($brightness / 100) * 254);
        $bri = max(1, min(254, $bri));
        return $this->setState(['bri' => $bri]);
    }

    public function setColor(string $hexColor): self
    {
        $rgb = $this->hexToRgb($hexColor);
        $xy = $this->rgbToXy($rgb);
        return $this->setState(['xy' => $xy]);
    }

    public function setColorTemperature(int $kelvin): self
    {
        $mired = (int) round(1000000 / $kelvin);
        $mired = max(153, min(500, $mired));
        return $this->setState(['ct' => $mired]);
    }

    public function setHue(int $hue): self
    {
        $hue = max(0, min(65535, $hue));
        return $this->setState(['hue' => $hue]);
    }

    public function setSaturation(int $saturation): self
    {
        $sat = (int) round(($saturation / 100) * 254);
        $sat = max(0, min(254, $sat));
        return $this->setState(['sat' => $sat]);
    }

    public function transition(int $milliseconds): self
    {
        $transitionTime = (int) round($milliseconds / 100);
        return $this->setState(['transitiontime' => $transitionTime]);
    }

    public function alert(string $mode = 'select'): self
    {
        return $this->setState(['alert' => $mode]);
    }

    public function effect(string $effect): self
    {
        return $this->setState(['effect' => $effect]);
    }

    public function setState(array $state): self
    {
        $this->client->request('PUT', "lights/{$this->id}/state", [
            'json' => $state
        ]);

        // Update local state
        foreach ($state as $key => $value) {
            $this->state->set($key, $value);
        }

        return $this;
    }

    public function animate(array $frames, int $repeat = 1): void
    {
        for ($i = 0; $i < $repeat; $i++) {
            foreach ($frames as $frame) {
                $state = [];

                if (isset($frame['color'])) {
                    $rgb = $this->hexToRgb($frame['color']);
                    $state['xy'] = $this->rgbToXy($rgb);
                }

                if (isset($frame['brightness'])) {
                    $state['bri'] = (int) round(($frame['brightness'] / 100) * 254);
                }

                if (isset($frame['duration'])) {
                    $state['transitiontime'] = (int) round($frame['duration'] / 100);
                }

                $this->setState($state);

                if (isset($frame['duration'])) {
                    usleep($frame['duration'] * 1000);
                }
            }
        }
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
