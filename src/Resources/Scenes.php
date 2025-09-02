<?php

namespace OguzhanTogay\HueClient\Resources;

use OguzhanTogay\HueClient\HueClient;

class Scenes
{
    private HueClient $client;

    public function __construct(HueClient $client)
    {
        $this->client = $client;
    }

    public function getAll(): array
    {
        $data = $this->client->request('GET', 'scenes');
        $scenes = [];

        foreach ($data as $id => $sceneData) {
            $scenes[] = new Scene($this->client, $id, $sceneData);
        }

        return $scenes;
    }

    public function get(string $id): Scene
    {
        $data = $this->client->request('GET', "scenes/{$id}");
        return new Scene($this->client, $id, $data);
    }

    public function getByName(string $name): ?Scene
    {
        $scenes = $this->getAll();

        foreach ($scenes as $scene) {
            if (strcasecmp($scene->getName(), $name) === 0) {
                return $scene;
            }
        }

        return null;
    }

    public function activate(string $sceneIdOrName): bool
    {
        $sceneId = $sceneIdOrName;

        // If it's not a scene ID, try to find by name
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $sceneIdOrName)) {
            $scene = $this->getByName($sceneIdOrName);
            if ($scene) {
                $sceneId = $scene->getId();
            } else {
                throw new \Exception("Scene '{$sceneIdOrName}' not found");
            }
        }

        $response = $this->client->request('PUT', "groups/0/action", [
            'json' => ['scene' => $sceneId]
        ]);

        return isset($response[0]['success']);
    }

    public function create(string $name, array $lights, ?string $type = null, ?string $group = null): Scene
    {
        $lightstates = [];
        foreach ($lights as $lightId => $state) {
            $lightstates[(string)$lightId] = $state;
        }

        $payload = [
            'name' => $name,
            'lights' => array_keys($lightstates),
            'lightstates' => $lightstates
        ];

        if ($type) {
            $payload['type'] = $type;
        }

        if ($group) {
            $payload['group'] = $group;
        }

        $response = $this->client->request('POST', 'scenes', [
            'json' => $payload
        ]);

        if (isset($response[0]['success']['id'])) {
            return $this->get($response[0]['success']['id']);
        }

        throw new \Exception('Failed to create scene');
    }

    public function delete(string $id): bool
    {
        $response = $this->client->request('DELETE', "scenes/{$id}");
        return isset($response[0]['success']);
    }

    public function recall(string $sceneId, ?int $groupId = null): bool
    {
        $groupId = $groupId ?? 0; // Default to all lights

        $response = $this->client->request('PUT', "groups/{$groupId}/action", [
            'json' => ['scene' => $sceneId]
        ]);

        return isset($response[0]['success']);
    }
}
