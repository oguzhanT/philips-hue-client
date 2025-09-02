<?php

namespace OguzhanTogay\HueClient\Resources;

use OguzhanTogay\HueClient\HueClient;

class Schedules
{
    private HueClient $client;

    public function __construct(HueClient $client)
    {
        $this->client = $client;
    }

    public function getAll(): array
    {
        $data = $this->client->request('GET', 'schedules');
        $schedules = [];

        foreach ($data as $id => $scheduleData) {
            $schedules[] = new Schedule($this->client, $id, $scheduleData);
        }

        return $schedules;
    }

    public function get(int $id): Schedule
    {
        $data = $this->client->request('GET', "schedules/{$id}");
        return new Schedule($this->client, $id, $data);
    }

    public function create(string $name, array $command, string $time, ?array $repeat = null): Schedule
    {
        $payload = [
            'name' => $name,
            'command' => $command,
            'localtime' => $time,
            'status' => 'enabled'
        ];

        if ($repeat) {
            // Convert day names to schedule format if needed
            $payload['localtime'] = $this->formatRecurringTime($time, $repeat);
        }

        $response = $this->client->request('POST', 'schedules', [
            'json' => $payload
        ]);

        if (isset($response[0]['success']['id'])) {
            return $this->get($response[0]['success']['id']);
        }

        throw new \Exception('Failed to create schedule');
    }

    public function once(string $name, array $command, string $dateTime): Schedule
    {
        return $this->create($name, $command, $dateTime);
    }

    public function atSunset(array $command, string $name = 'Sunset Schedule', int $offset = 0): Schedule
    {
        $time = 'W127/T23:00:00'; // Weekly recurring at sunset
        if ($offset !== 0) {
            $time .= sprintf('A%02d:%02d:%02d', 0, 0, abs($offset));
        }

        return $this->create($name, $command, $time);
    }

    public function atSunrise(array $command, string $name = 'Sunrise Schedule', int $offset = 0): Schedule
    {
        $time = 'W127/T06:00:00'; // Weekly recurring at sunrise
        if ($offset !== 0) {
            $time .= sprintf('A%02d:%02d:%02d', 0, 0, abs($offset));
        }

        return $this->create($name, $command, $time);
    }

    public function delete(int $id): bool
    {
        $response = $this->client->request('DELETE', "schedules/{$id}");
        return isset($response[0]['success']);
    }

    private function formatRecurringTime(string $time, array $repeat): string
    {
        $dayMap = [
            'monday' => 64,
            'tuesday' => 32,
            'wednesday' => 16,
            'thursday' => 8,
            'friday' => 4,
            'saturday' => 2,
            'sunday' => 1
        ];

        $dayMask = 0;
        foreach ($repeat as $day) {
            if (isset($dayMap[strtolower($day)])) {
                $dayMask |= $dayMap[strtolower($day)];
            }
        }

        return "W{$dayMask}/T{$time}";
    }
}
