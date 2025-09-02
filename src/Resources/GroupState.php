<?php

namespace OguzhanTogay\HueClient\Resources;

class GroupState
{
    private array $state;

    public function __construct(array $state = [])
    {
        $this->state = $state;
    }

    public function anyOn(): bool
    {
        return $this->state['any_on'] ?? false;
    }

    public function allOn(): bool
    {
        return $this->state['all_on'] ?? false;
    }

    public function toArray(): array
    {
        return $this->state;
    }
}
