<?php

namespace OguzhanTogay\HueClient\Effects;

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Resources\Light;
use OguzhanTogay\HueClient\Resources\Group;

abstract class BaseEffect
{
    protected HueClient $client;
    protected bool $running = false;

    public function __construct(HueClient $client)
    {
        $this->client = $client;
    }

    abstract public function start($target, ...$args): void;

    public function stop(): void
    {
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    protected function isLight($target): bool
    {
        return $target instanceof Light;
    }

    protected function isGroup($target): bool
    {
        return $target instanceof Group;
    }

    protected function setLightState($target, array $state): void
    {
        if ($this->isLight($target)) {
            $target->setState($state);
        } elseif ($this->isGroup($target)) {
            $target->setAction($state);
        }
    }

    protected function sleep(int $milliseconds): void
    {
        if ($this->running) {
            usleep($milliseconds * 1000);
        }
    }
}