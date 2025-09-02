<?php

namespace OguzhanTogay\HueClient\Resources;

use OguzhanTogay\HueClient\HueClient;

class Schedule
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

    public function getCommand(): array
    {
        return $this->attributes['command'] ?? [];
    }

    public function getLocalTime(): string
    {
        return $this->attributes['localtime'] ?? '';
    }

    public function getStatus(): string
    {
        return $this->attributes['status'] ?? 'disabled';
    }

    public function isEnabled(): bool
    {
        return $this->getStatus() === 'enabled';
    }

    public function getDescription(): string
    {
        return $this->attributes['description'] ?? '';
    }

    public function getCreated(): ?string
    {
        return $this->attributes['created'] ?? null;
    }

    public function enable(): bool
    {
        return $this->modify(['status' => 'enabled']);
    }

    public function disable(): bool
    {
        return $this->modify(['status' => 'disabled']);
    }

    public function modify(array $updates): bool
    {
        $response = $this->client->request('PUT', "schedules/{$this->id}", [
            'json' => $updates
        ]);

        // Update local attributes
        foreach ($updates as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return isset($response[0]['success']);
    }

    public function delete(): bool
    {
        $response = $this->client->request('DELETE', "schedules/{$this->id}");
        return isset($response[0]['success']);
    }

    public function toArray(): array
    {
        return array_merge(['id' => $this->id], $this->attributes);
    }
}
