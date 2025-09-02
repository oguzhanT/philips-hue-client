<?php

namespace OguzhanTogay\HueClient\Resources;

use OguzhanTogay\HueClient\HueClient;

class Sensors
{
    private HueClient $client;

    public function __construct(HueClient $client)
    {
        $this->client = $client;
    }

    public function getAll(): array
    {
        $data = $this->client->request('GET', 'sensors');
        $sensors = [];

        foreach ($data as $id => $sensorData) {
            $sensors[] = new Sensor($this->client, $id, $sensorData);
        }

        return $sensors;
    }

    public function get(int $id): Sensor
    {
        $data = $this->client->request('GET', "sensors/{$id}");
        return new Sensor($this->client, $id, $data);
    }

    public function getByType(string $type): array
    {
        $sensors = $this->getAll();
        return array_filter($sensors, fn($s) => $s->getType() === $type);
    }

    public function search(): array
    {
        return $this->client->request('POST', 'sensors');
    }

    public function getNew(): array
    {
        return $this->client->request('GET', 'sensors/new');
    }

    public function create(string $name, string $type, array $config = []): Sensor
    {
        $payload = [
            'name' => $name,
            'type' => $type,
            'config' => $config
        ];

        $response = $this->client->request('POST', 'sensors', [
            'json' => $payload
        ]);

        if (isset($response[0]['success']['id'])) {
            return $this->get($response[0]['success']['id']);
        }

        throw new \Exception('Failed to create sensor');
    }

    public function delete(int $id): bool
    {
        $response = $this->client->request('DELETE', "sensors/{$id}");
        return isset($response[0]['success']);
    }
}
