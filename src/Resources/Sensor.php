<?php

namespace OguzhanTogay\HueClient\Resources;

use OguzhanTogay\HueClient\HueClient;

class Sensor
{
    private HueClient $client;
    private int $id;
    private array $attributes;

    public function __construct(HueClient $client, int $id, array $attributes)
    {
        $this->client = $client;
        $this->id = $id;
        $this->attributes = $attributes;
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

    public function getUniqueId(): ?string
    {
        return $this->attributes['uniqueid'] ?? null;
    }

    public function getSwVersion(): ?string
    {
        return $this->attributes['swversion'] ?? null;
    }

    public function getConfig(): array
    {
        return $this->attributes['config'] ?? [];
    }

    public function getState(): array
    {
        return $this->attributes['state'] ?? [];
    }

    public function isConfigured(): bool
    {
        return ($this->attributes['config']['configured'] ?? false) === true;
    }

    public function isReachable(): bool
    {
        return ($this->attributes['config']['reachable'] ?? false) === true;
    }

    public function getBattery(): ?int
    {
        return $this->attributes['config']['battery'] ?? null;
    }

    public function updateConfig(array $config): bool
    {
        $response = $this->client->request('PUT', "sensors/{$this->id}/config", [
            'json' => $config
        ]);

        return isset($response[0]['success']);
    }

    public function updateState(array $state): bool
    {
        $response = $this->client->request('PUT', "sensors/{$this->id}/state", [
            'json' => $state
        ]);

        return isset($response[0]['success']);
    }

    public function toArray(): array
    {
        return array_merge(['id' => $this->id], $this->attributes);
    }
}
