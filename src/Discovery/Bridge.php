<?php

namespace OguzhanTogay\HueClient\Discovery;

class Bridge
{
    private string $id;
    private string $ip;
    private int $port;
    private ?string $name;
    private ?string $modelId;
    private ?string $swVersion;

    public function __construct(
        string $id,
        string $ip,
        int $port = 443,
        ?string $name = null,
        ?string $modelId = null,
        ?string $swVersion = null
    ) {
        $this->id = $id;
        $this->ip = $ip;
        $this->port = $port;
        $this->name = $name;
        $this->modelId = $modelId;
        $this->swVersion = $swVersion;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getModelId(): ?string
    {
        return $this->modelId;
    }

    public function getSwVersion(): ?string
    {
        return $this->swVersion;
    }

    public function getBaseUrl(): string
    {
        return "https://{$this->ip}:{$this->port}";
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ip' => $this->ip,
            'port' => $this->port,
            'name' => $this->name,
            'model_id' => $this->modelId,
            'sw_version' => $this->swVersion,
        ];
    }
}
