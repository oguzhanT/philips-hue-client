<?php

namespace OguzhanTogay\HueClient\V2\Resources;

use OguzhanTogay\HueClient\Exceptions\HueException;
use OguzhanTogay\HueClient\V2\HueV2Client;

/**
 * @template T of Resource
 */
class ResourceCollection
{
    /**
     * @param class-string<T> $class
     */
    public function __construct(
        private readonly HueV2Client $client,
        private readonly string $type,
        private readonly string $class
    ) {
    }

    /**
     * @return T[]
     */
    public function all(): array
    {
        $items = $this->client->request('GET', "resource/{$this->type}");

        return array_map(fn (array $data) => $this->make($data), $items);
    }

    /**
     * @return T
     */
    public function get(string $id): Resource
    {
        $data = $this->client->request('GET', "resource/{$this->type}/{$id}")[0]
            ?? throw new HueException("No {$this->type} with id {$id}.");

        return $this->make($data);
    }

    /**
     * Case-insensitive match on metadata.name.
     *
     * @return T
     */
    public function byName(string $name): Resource
    {
        foreach ($this->all() as $resource) {
            if (strcasecmp($resource->name(), $name) === 0) {
                return $resource;
            }
        }

        throw new HueException("No {$this->type} named '{$name}'.");
    }

    private function make(array $data): Resource
    {
        return new ($this->class)($this->client, $data);
    }
}
