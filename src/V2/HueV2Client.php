<?php

namespace OguzhanTogay\HueClient\V2;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use OguzhanTogay\HueClient\Exceptions\HueException;
use OguzhanTogay\HueClient\V2\Resources\Device;
use OguzhanTogay\HueClient\V2\Resources\GroupedLight;
use OguzhanTogay\HueClient\V2\Resources\Light;
use OguzhanTogay\HueClient\V2\Resources\Resource;
use OguzhanTogay\HueClient\V2\Resources\ResourceCollection;
use OguzhanTogay\HueClient\V2\Resources\Room;
use OguzhanTogay\HueClient\V2\Resources\Scene;

/**
 * Client for the bridge's CLIP v2 API (https://<bridge>/clip/v2).
 *
 *   $hue = new HueV2Client('192.168.1.2', $applicationKey);
 *   $hue->lights()->byName('Desk')->on()->color('#FF5733')->brightness(60);
 *   $hue->rooms()->byName('Living room')->groupedLight()->off();
 *   $hue->scenes()->byName('Relax')->recall();
 *   $hue->events()->listen(fn (array $event) => print_r($event));
 *
 * The application key is the same value the v1 API calls the username.
 *
 * TLS: the bridge serves a certificate signed by Philips' own CA, so the
 * default 'verify' => false accepts it without checking. Pin it instead:
 * 'pinned_public_key' => BridgeCertificate::fetch($ip)->pin (read once, on a
 * network you trust).
 */
class HueV2Client
{
    private ClientInterface $http;
    private array $options;
    /** @var array<string, ResourceCollection> */
    private array $collections = [];

    /**
     * @param array{timeout?: float, connect_timeout?: float, verify?: bool|string,
     *              pinned_public_key?: ?string} $options
     */
    public function __construct(
        private readonly string $bridgeIp,
        private readonly string $applicationKey,
        array $options = [],
        ?ClientInterface $http = null
    ) {
        $this->options = $options + ['timeout' => 5, 'connect_timeout' => 3, 'verify' => false];
        $this->http = $http ?? new Client([
            'base_uri' => "https://{$bridgeIp}/",
            'timeout' => $this->options['timeout'],
            'connect_timeout' => $this->options['connect_timeout'],
            'verify' => $this->options['verify'],
            'http_errors' => false,
        ] + \OguzhanTogay\HueClient\HueClient::pinOptions($this->options['pinned_public_key'] ?? null));
    }

    /** @return ResourceCollection<Light> */
    public function lights(): ResourceCollection
    {
        return $this->collection('light', Light::class);
    }

    /** @return ResourceCollection<Room> */
    public function rooms(): ResourceCollection
    {
        return $this->collection('room', Room::class);
    }

    /** @return ResourceCollection<Room> zones behave like rooms */
    public function zones(): ResourceCollection
    {
        return $this->collection('zone', Room::class);
    }

    /** @return ResourceCollection<GroupedLight> */
    public function groupedLights(): ResourceCollection
    {
        return $this->collection('grouped_light', GroupedLight::class);
    }

    /** @return ResourceCollection<Scene> */
    public function scenes(): ResourceCollection
    {
        return $this->collection('scene', Scene::class);
    }

    /** @return ResourceCollection<Device> */
    public function devices(): ResourceCollection
    {
        return $this->collection('device', Device::class);
    }

    /**
     * Any other resource type (motion, temperature, button, bridge, ...).
     *
     * @return ResourceCollection<Resource>
     */
    public function resources(string $type): ResourceCollection
    {
        return $this->collection($type, Resource::class);
    }

    public function events(): EventStream
    {
        return new EventStream($this->http, $this->applicationKey, fn (string $m): string => $this->redact($m));
    }

    /**
     * @return array<int, array> the response's data list
     * @throws HueException when the bridge reports errors or cannot be reached
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $options = ['headers' => ['hue-application-key' => $this->applicationKey]];
        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $this->http->request($method, 'clip/v2/' . ltrim($path, '/'), $options);
        } catch (GuzzleException $e) {
            throw new HueException('Request failed: ' . $this->redact($e->getMessage()));
        }

        $decoded = json_decode((string) $response->getBody(), true);
        $errors = array_filter(array_column(is_array($decoded) ? ($decoded['errors'] ?? []) : [], 'description'));

        if ($errors !== []) {
            throw new HueException(implode('; ', $errors));
        }

        if ($response->getStatusCode() >= 400 || !is_array($decoded)) {
            throw new HueException("Bridge answered HTTP {$response->getStatusCode()} for {$method} {$path}");
        }

        return $decoded['data'] ?? [];
    }

    public function getBridgeIp(): string
    {
        return $this->bridgeIp;
    }

    public function redact(string $message): string
    {
        return str_replace($this->applicationKey, '***', $message);
    }

    private function collection(string $type, string $class): ResourceCollection
    {
        return $this->collections[$type] ??= new ResourceCollection($this, $type, $class);
    }
}
