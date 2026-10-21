<?php

namespace OguzhanTogay\HueClient\Discovery;

use GuzzleHttp\Client;
use OguzhanTogay\HueClient\Exceptions\HueException;

class BridgeDiscovery
{
    private Client $httpClient;
    /** Local multicast DNS first: it needs no internet and no Philips account. */
    private array $discoveryMethods = ['mdns', 'nupnp'];

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 5,
            'http_errors' => false,
        ]);
    }

    public function discover(): array
    {
        $bridges = [];

        foreach ($this->discoveryMethods as $method) {
            try {
                $discovered = $this->{"discover" . ucfirst($method)}();
                $bridges = array_merge($bridges, $discovered);
            } catch (\Exception $e) {
                // Continue with next discovery method
            }
        }

        // Remove duplicates based on bridge ID
        $uniqueBridges = [];
        $seenIds = [];

        foreach ($bridges as $bridge) {
            // mDNS and the discovery service spell the same id in different cases.
            $id = strtolower($bridge->getId());
            if (!in_array($id, $seenIds, true)) {
                $uniqueBridges[] = $bridge;
                $seenIds[] = $id;
            }
        }

        return $uniqueBridges;
    }

    private function discoverNupnp(): array
    {
        $response = $this->httpClient->get('https://discovery.meethue.com/');
        $data = json_decode($response->getBody()->getContents(), true);

        if (!is_array($data)) {
            return [];
        }

        $bridges = [];
        foreach ($data as $bridgeData) {
            if (isset($bridgeData['id']) && isset($bridgeData['internalipaddress'])) {
                $bridges[] = new Bridge(
                    $bridgeData['id'],
                    $bridgeData['internalipaddress'],
                    $bridgeData['port'] ?? 443
                );
            }
        }

        return $bridges;
    }

    private function discoverMdns(): array
    {
        return (new MdnsDiscovery())->discover();
    }

    public function discoverByIp(string $ip): ?Bridge
    {
        try {
            $response = $this->httpClient->get("https://{$ip}/api/config", [
                'verify' => false
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['bridgeid'])) {
                return new Bridge(
                    $data['bridgeid'],
                    $ip,
                    443,
                    $data['name'] ?? null,
                    $data['modelid'] ?? null,
                    $data['swversion'] ?? null
                );
            }
        } catch (\Exception $e) {
            // Bridge not found at this IP
        }

        return null;
    }
}
