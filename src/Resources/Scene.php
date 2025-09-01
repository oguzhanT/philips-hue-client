<?php

namespace OguzhanTogay\HueClient\Resources;

use OguzhanTogay\HueClient\HueClient;

class Scene
{
    private HueClient $client;
    private string $id;
    private array $attributes;

    public function __construct(HueClient $client, string $id, array $attributes)
    {
        $this->client = $client;
        $this->id = $id;
        $this->attributes = $attributes;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->attributes['name'] ?? '';
    }

    public function getType(): string
    {
        return $this->attributes['type'] ?? 'LightScene';
    }

    public function getGroup(): ?string
    {
        return $this->attributes['group'] ?? null;
    }

    public function getLights(): array
    {
        return $this->attributes['lights'] ?? [];
    }

    public function getLightStates(): array
    {
        return $this->attributes['lightstates'] ?? [];
    }

    public function getOwner(): ?string
    {
        return $this->attributes['owner'] ?? null;
    }

    public function isRecycle(): bool
    {
        return $this->attributes['recycle'] ?? false;
    }

    public function isLocked(): bool
    {
        return $this->attributes['locked'] ?? false;
    }

    public function getLastUpdated(): ?string
    {
        return $this->attributes['lastupdated'] ?? null;
    }

    public function getPicture(): ?string
    {
        return $this->attributes['picture'] ?? null;
    }

    public function activate(?int $groupId = null): bool
    {
        $groupId = $groupId ?? 0; // Default to all lights

        $response = $this->client->request('PUT', "groups/{$groupId}/action", [
            'json' => ['scene' => $this->id]
        ]);

        return isset($response[0]['success']);
    }

    public function modify(array $updates): bool
    {
        $response = $this->client->request('PUT', "scenes/{$this->id}", [
            'json' => $updates
        ]);

        return isset($response[0]['success']);
    }

    public function setLightState(int $lightId, array $state): bool
    {
        $response = $this->client->request('PUT', "scenes/{$this->id}/lightstates/{$lightId}", [
            'json' => $state
        ]);

        return isset($response[0]['success']);
    }

    public function delete(): bool
    {
        $response = $this->client->request('DELETE', "scenes/{$this->id}");
        return isset($response[0]['success']);
    }

    public function toArray(): array
    {
        return array_merge(['id' => $this->id], $this->attributes);
    }
}