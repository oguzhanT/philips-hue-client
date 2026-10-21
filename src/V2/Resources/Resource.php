<?php

namespace OguzhanTogay\HueClient\V2\Resources;

use OguzhanTogay\HueClient\V2\HueV2Client;

/**
 * Any CLIP v2 resource: its id, type and the bridge's JSON as an array.
 */
class Resource
{
    public function __construct(
        protected readonly HueV2Client $client,
        protected array $data
    ) {
    }

    public function id(): string
    {
        return (string) $this->data['id'];
    }

    public function type(): string
    {
        return (string) $this->data['type'];
    }

    public function name(): string
    {
        return (string) ($this->data['metadata']['name'] ?? '');
    }

    /**
     * The bridge's JSON for this resource.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Sends a partial update (PUT /resource/<type>/<id>) and merges it into
     * the local copy.
     */
    public function update(array $changes): static
    {
        $this->client->request('PUT', "resource/{$this->type()}/{$this->id()}", $changes);
        $this->data = array_replace_recursive($this->data, $changes);

        return $this;
    }

    public function refresh(): static
    {
        $this->data = $this->client->request('GET', "resource/{$this->type()}/{$this->id()}")[0] ?? $this->data;

        return $this;
    }

    /**
     * The ids of the related resources of a type, from children or services.
     *
     * @return string[]
     */
    protected function references(string $key, string $rtype): array
    {
        $ids = [];
        foreach ($this->data[$key] ?? [] as $reference) {
            if (($reference['rtype'] ?? null) === $rtype) {
                $ids[] = (string) $reference['rid'];
            }
        }

        return $ids;
    }
}
