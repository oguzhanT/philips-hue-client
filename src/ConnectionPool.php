<?php

namespace OguzhanTogay\HueClient;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ConnectionPool
{
    private array $bridges = [];
    private array $clients = [];
    private LoggerInterface $logger;
    private int $maxConnections;
    private int $timeout;

    public function __construct(
        array $bridges = [],
        int $maxConnections = 10,
        int $timeout = 5,
        ?LoggerInterface $logger = null
    ) {
        $this->bridges = $bridges;
        $this->maxConnections = $maxConnections;
        $this->timeout = $timeout;
        $this->logger = $logger ?? new NullLogger();
    }

    public function addBridge(string $ip, string $username): void
    {
        $this->bridges[$ip] = $username;
        $this->createClient($ip, $username);
    }

    public function removeBridge(string $ip): void
    {
        unset($this->bridges[$ip]);
        unset($this->clients[$ip]);
    }

    public function getClient(string $ip): ?HueClient
    {
        if (!isset($this->clients[$ip]) && isset($this->bridges[$ip])) {
            $this->createClient($ip, $this->bridges[$ip]);
        }

        return $this->clients[$ip] ?? null;
    }

    public function getAllClients(): array
    {
        return $this->clients;
    }

    public function healthCheck(): array
    {
        $results = [];
        $requests = [];

        foreach ($this->bridges as $ip => $username) {
            $requests[] = new Request('GET', "https://{$ip}/api/{$username}/config");
        }

        if (empty($requests)) {
            return $results;
        }

        $client = new Client(['timeout' => $this->timeout, 'verify' => false]);
        $pool = new Pool($client, $requests, [
            'concurrency' => $this->maxConnections,
            'fulfilled' => function ($response, $index) use (&$results) {
                $ip = array_keys($this->bridges)[$index];
                $results[$ip] = [
                    'status' => 'healthy',
                    'response_time' => $response->getHeader('X-Response-Time')[0] ?? 'unknown',
                    'last_check' => date('c')
                ];
            },
            'rejected' => function ($reason, $index) use (&$results) {
                $ip = array_keys($this->bridges)[$index];
                $results[$ip] = [
                    'status' => 'unhealthy',
                    'error' => (string) $reason,
                    'last_check' => date('c')
                ];
            },
        ]);

        $pool->promise()->wait();

        return $results;
    }

    public function broadcastToAll(callable $action): array
    {
        $results = [];

        foreach ($this->clients as $ip => $client) {
            try {
                $results[$ip] = [
                    'success' => true,
                    'result' => $action($client),
                    'timestamp' => date('c')
                ];
            } catch (\Exception $e) {
                $results[$ip] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'timestamp' => date('c')
                ];
            }
        }

        return $results;
    }

    public function getBridgeCount(): int
    {
        return count($this->bridges);
    }

    public function getActiveConnections(): int
    {
        return count(array_filter($this->clients, function ($client) {
            return $client->isConnected();
        }));
    }

    private function createClient(string $ip, string $username): void
    {
        try {
            $client = new HueClient($ip, $username, [
                'timeout' => $this->timeout
            ], $this->logger);

            if ($client->isConnected()) {
                $this->clients[$ip] = $client;
                $this->logger->info("Connected to bridge", ['ip' => $ip]);
            } else {
                $this->logger->warning("Failed to connect to bridge", ['ip' => $ip]);
            }
        } catch (\Exception $e) {
            $this->logger->error("Error creating client for bridge", [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
        }
    }
}
