<?php

namespace OguzhanTogay\HueClient\Resources;

use OguzhanTogay\HueClient\Color\Color;
use OguzhanTogay\HueClient\HueClient;

class Group
{
    private HueClient $client;
    private int $id;
    private array $attributes;
    private GroupState $state;
    private GroupAction $action;

    public function __construct(HueClient $client, int $id, array $attributes)
    {
        $this->client = $client;
        $this->id = $id;
        $this->attributes = $attributes;
        $this->state = new GroupState($attributes['state'] ?? []);
        $this->action = new GroupAction($attributes['action'] ?? []);
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

    public function getClass(): ?string
    {
        return $this->attributes['class'] ?? null;
    }

    public function getLights(): array
    {
        return $this->attributes['lights'] ?? [];
    }

    public function getState(): GroupState
    {
        return $this->state;
    }

    public function getAction(): GroupAction
    {
        return $this->action;
    }

    public function refresh(): self
    {
        $data = $this->client->request('GET', "groups/{$this->id}");
        $this->attributes = $data;
        $this->state = new GroupState($data['state'] ?? []);
        $this->action = new GroupAction($data['action'] ?? []);
        return $this;
    }

    public function on(): self
    {
        return $this->setAction(['on' => true]);
    }

    public function off(): self
    {
        return $this->setAction(['on' => false]);
    }

    public function toggle(): self
    {
        return $this->setAction(['on' => !$this->state->anyOn()]);
    }

    public function setBrightness(int $brightness): self
    {
        $bri = (int) round(($brightness / 100) * 254);
        $bri = max(1, min(254, $bri));
        return $this->setAction(['bri' => $bri]);
    }

    public function dim(int $percentage): self
    {
        return $this->setBrightness($percentage);
    }

    public function setColor(string $hexColor): self
    {
        $xy = Color::fromHex($hexColor)->toXy();
        return $this->setAction(['xy' => $xy]);
    }

    public function setColorTemperature(int $kelvin): self
    {
        $mired = (int) round(1000000 / $kelvin);
        $mired = max(153, min(500, $mired));
        return $this->setAction(['ct' => $mired]);
    }

    public function setScene(string $sceneIdOrName): self
    {
        // If it's not a scene ID, try to find by name
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $sceneIdOrName)) {
            $scenes = $this->client->scenes()->getAll();
            foreach ($scenes as $scene) {
                if (strcasecmp($scene->getName(), $sceneIdOrName) === 0) {
                    $sceneIdOrName = $scene->getId();
                    break;
                }
            }
        }

        return $this->setAction(['scene' => $sceneIdOrName]);
    }

    public function alert(string $mode = 'select'): self
    {
        return $this->setAction(['alert' => $mode]);
    }

    public function effect(string $effect): self
    {
        return $this->setAction(['effect' => $effect]);
    }

    public function transition(int $milliseconds): self
    {
        $transitionTime = (int) round($milliseconds / 100);
        return $this->setAction(['transitiontime' => $transitionTime]);
    }

    public function setAction(array $action): self
    {
        $this->client->request('PUT', "groups/{$this->id}/action", [
            'json' => $action
        ]);

        // Update local action
        foreach ($action as $key => $value) {
            $this->action->set($key, $value);
        }

        return $this;
    }

    public function party(): self
    {
        return $this->effect('colorloop');
    }

    public function sunrise(int $duration = 600): self
    {
        $steps = 10;
        $stepDuration = $duration / $steps;

        // Start dark and gradually brighten with warm colors
        $this->off();
        sleep(1);

        for ($i = 1; $i <= $steps; $i++) {
            $brightness = (int) (($i / $steps) * 100);
            $temperature = 2000 + (int) ((4500 - 2000) * ($i / $steps));

            $this->on()
                ->setBrightness($brightness)
                ->setColorTemperature($temperature)
                ->transition($stepDuration * 1000);

            sleep($stepDuration);
        }

        return $this;
    }

    public function sunset(int $duration = 600): self
    {
        $steps = 10;
        $stepDuration = $duration / $steps;

        for ($i = $steps; $i >= 1; $i--) {
            $brightness = (int) (($i / $steps) * 100);
            $temperature = 6500 - (int) ((6500 - 2000) * (($steps - $i) / $steps));

            $this->setBrightness($brightness)
                ->setColorTemperature($temperature)
                ->transition($stepDuration * 1000);

            sleep($stepDuration);
        }

        $this->off();
        return $this;
    }
}
