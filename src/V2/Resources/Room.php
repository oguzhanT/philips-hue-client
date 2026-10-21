<?php

namespace OguzhanTogay\HueClient\V2\Resources;

/**
 * A room or a zone.
 */
class Room extends Resource
{
    /**
     * Switches the room's lights together.
     */
    public function groupedLight(): GroupedLight
    {
        $id = $this->references('services', 'grouped_light')[0]
            ?? throw new \LogicException("'{$this->name()}' has no grouped light.");

        return $this->client->groupedLights()->get($id);
    }

    /**
     * The lights in this room (rooms list devices, zones list lights).
     *
     * @return Light[]
     */
    public function lights(): array
    {
        $lightIds = $this->references('children', 'light');

        foreach ($this->references('children', 'device') as $deviceId) {
            $lightIds = array_merge($lightIds, $this->client->devices()->get($deviceId)->lightIds());
        }

        return array_map(
            fn (string $id): Light => $this->client->lights()->get($id),
            array_values(array_unique($lightIds))
        );
    }

    /**
     * @return Scene[] the scenes made for this room
     */
    public function scenes(): array
    {
        return array_values(array_filter(
            $this->client->scenes()->all(),
            fn (Scene $scene): bool => $scene->groupId() === $this->id()
        ));
    }
}
