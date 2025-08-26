<?php

namespace OguzhanTogay\HueClient\Resources;

use OguzhanTogay\HueClient\HueClient;

class Groups
{
    private HueClient $client;

    public function __construct(HueClient $client)
    {
        $this->client = $client;
    }

    public function getAll(): array
    {
        $data = $this->client->request('GET', 'groups');
        $groups = [];

        foreach ($data as $id => $groupData) {
            $groups[] = new Group($this->client, $id, $groupData);
        }

        return $groups;
    }

    public function get(int $id): Group
    {
        $data = $this->client->request('GET', "groups/{$id}");
        return new Group($this->client, $id, $data);
    }

    public function getByName(string $name): ?Group
    {
        $groups = $this->getAll();
        
        foreach ($groups as $group) {
            if (strcasecmp($group->getName(), $name) === 0) {
                return $group;
            }
        }

        return null;
    }

    public function getRooms(): array
    {
        $groups = $this->getAll();
        return array_filter($groups, fn($g) => $g->getType() === 'Room');
    }

    public function getZones(): array
    {
        $groups = $this->getAll();
        return array_filter($groups, fn($g) => $g->getType() === 'Zone');
    }

    public function all(): Group
    {
        // Group 0 represents all lights
        return $this->get(0);
    }

    public function create(string $name, array $lightIds, string $type = 'LightGroup', ?string $class = null): Group
    {
        $payload = [
            'name' => $name,
            'lights' => array_map('strval', $lightIds),
            'type' => $type
        ];

        if ($class && $type === 'Room') {
            $payload['class'] = $class;
        }

        $response = $this->client->request('POST', 'groups', [
            'json' => $payload
        ]);

        if (isset($response[0]['success']['id'])) {
            return $this->get($response[0]['success']['id']);
        }

        throw new \Exception('Failed to create group');
    }

    public function delete(int $id): bool
    {
        if ($id === 0) {
            throw new \Exception('Cannot delete group 0 (all lights)');
        }

        $response = $this->client->request('DELETE', "groups/{$id}");
        return isset($response[0]['success']);
    }
}