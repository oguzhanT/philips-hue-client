<?php

namespace OguzhanTogay\HueClient\Discovery;

use GuzzleHttp\Client;
use OguzhanTogay\HueClient\Exceptions\HueException;

class BridgeDiscovery
{
    private Client $httpClient;
    private array $discoveryMethods = ['nupnp', 'mdns', 'ssdp'];

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
            if (!in_array($bridge->getId(), $seenIds)) {
                $uniqueBridges[] = $bridge;
                $seenIds[] = $bridge->getId();
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
        // mDNS discovery implementation
        // This would require additional libraries for mDNS/Bonjour discovery
        // For now, returning empty array
        return [];
    }

    private function discoverSsdp(): array
    {
        // SSDP discovery implementation
        // This would require socket programming for SSDP/UPnP discovery
        // For now, returning empty array
        return [];
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