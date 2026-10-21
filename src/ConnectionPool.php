<?php

namespace OguzhanTogay\HueClient;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ConnectionPool
{
    private array $bridges = [];
    /** @var array<string, string> public key pin per bridge ip */
    private array $pins = [];
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

    /**
     * @param string|null $pinnedPublicKey see Security\BridgeCertificate
     */
    public function addBridge(string $ip, string $username, ?string $pinnedPublicKey = null): void
    {
        $this->bridges[$ip] = $username;
        if ($pinnedPublicKey !== null && $pinnedPublicKey !== '') {
            $this->pins[$ip] = $pinnedPublicKey;
        }
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

    /**
     * Each bridge's reachability, through a pinned, redacting client.
     */
    public function healthCheck(): array
    {
        $results = [];

        foreach ($this->bridges as $ip => $username) {
            $started = microtime(true);
            // One attempt, no retries or cache: an unreachable bridge should
            // cost one timeout, not the retry schedule.
            $probe = new HueClient((string) $ip, $username, [
                'timeout' => $this->timeout,
                'retry_attempts' => 0,
                'cache_enabled' => false,
                'pinned_public_key' => $this->pins[$ip] ?? null,
            ], $this->logger);
            $healthy = $probe->isConnected();

            $results[$ip] = [
                'status' => $healthy ? 'healthy' : 'unhealthy',
                'response_time' => round((microtime(true) - $started) * 1000) . ' ms',
                'last_check' => date('c'),
            ];
        }

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

    public function getMaxConnections(): int
    {
        return $this->maxConnections;
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
                'timeout' => $this->timeout,
                'pinned_public_key' => $this->pins[$ip] ?? null,
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
