<?php

namespace OguzhanTogay\HueClient\Resources;

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Exceptions\HueException;

class Lights
{
    private HueClient $client;
    private array $cache = [];

    public function __construct(HueClient $client)
    {
        $this->client = $client;
    }

    public function getAll(): array
    {
        $data = $this->client->request('GET', 'lights');
        $lights = [];

        foreach ($data as $id => $lightData) {
            $lights[] = new Light($this->client, $id, $lightData);
        }

        $this->cache = $lights;
        return $lights;
    }

    public function get(int $id): Light
    {
        $data = $this->client->request('GET', "lights/{$id}");
        return new Light($this->client, $id, $data);
    }

    public function getByName(string $name): ?Light
    {
        $lights = $this->getAll();
        
        foreach ($lights as $light) {
            if (strcasecmp($light->getName(), $name) === 0) {
                return $light;
            }
        }

        return null;
    }

    public function search(): array
    {
        return $this->client->request('POST', 'lights');
    }

    public function getNew(): array
    {
        return $this->client->request('GET', 'lights/new');
    }

    public function rename(int $id, string $name): bool
    {
        $response = $this->client->request('PUT', "lights/{$id}", [
            'json' => ['name' => $name]
        ]);

        return isset($response[0]['success']);
    }

    public function delete(int $id): bool
    {
        $response = $this->client->request('DELETE', "lights/{$id}");
        return isset($response[0]['success']);
    }
}