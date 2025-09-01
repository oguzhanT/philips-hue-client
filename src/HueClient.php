<?php

namespace OguzhanTogay\HueClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use OguzhanTogay\HueClient\Exceptions\HueException;
use OguzhanTogay\HueClient\Exceptions\AuthenticationException;
use OguzhanTogay\HueClient\Resources\Lights;
use OguzhanTogay\HueClient\Resources\Groups;
use OguzhanTogay\HueClient\Resources\Scenes;
use OguzhanTogay\HueClient\Resources\Schedules;
use OguzhanTogay\HueClient\Resources\Events;
use OguzhanTogay\HueClient\Resources\Sensors;
use OguzhanTogay\HueClient\Retry\RetryHandler;
use OguzhanTogay\HueClient\Cache\HueCache;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class HueClient
{
    private Client $httpClient;
    private ?string $username;
    private string $bridgeIp;
    private LoggerInterface $logger;
    private array $config;
    private RetryHandler $retryHandler;
    private ?HueCache $cache;
    
    private ?Lights $lights = null;
    private ?Groups $groups = null;
    private ?Scenes $scenes = null;
    private ?Schedules $schedules = null;
    private ?Events $events = null;
    private ?Sensors $sensors = null;

    public function __construct(
        string $bridgeIp,
        ?string $username = null,
        array $config = [],
        ?LoggerInterface $logger = null
    ) {
        $this->bridgeIp = $bridgeIp;
        $this->username = $username;
        $this->logger = $logger ?? new NullLogger();
        $this->config = array_merge([
            'timeout' => 5,
            'verify' => false,
            'http_errors' => false,
            'retry_attempts' => 3,
            'cache_enabled' => true,
            'cache_type' => 'filesystem'
        ], $config);

        $this->httpClient = new Client([
            'base_uri' => "https://{$bridgeIp}/api/",
            'timeout' => $this->config['timeout'],
            'verify' => $this->config['verify'],
            'http_errors' => $this->config['http_errors'],
        ]);

        $this->retryHandler = new RetryHandler(
            $this->config['retry_attempts'],
            [1, 2, 4],
            $this->logger
        );

        if ($this->config['cache_enabled']) {
            $this->cache = new HueCache(
                $this->config['cache_type'],
                $config,
                $this->logger
            );
        }
    }

    public function register(string $appName, string $deviceName = null): string
    {
        $deviceName = $deviceName ?? gethostname();
        
        $payload = [
            'devicetype' => "{$appName}#{$deviceName}"
        ];

        try {
            $response = $this->httpClient->post('', [
                'json' => $payload
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data[0]['error'])) {
                if ($data[0]['error']['type'] === 101) {
                    throw new AuthenticationException(
                        'Link button not pressed. Please press the link button on the Hue Bridge and try again.'
                    );
                }
                throw new HueException($data[0]['error']['description']);
            }

            if (isset($data[0]['success']['username'])) {
                $this->username = $data[0]['success']['username'];
                $this->logger->info('Successfully registered with Hue Bridge', [
                    'username' => $this->username,
                    'bridge_ip' => $this->bridgeIp
                ]);
                return $this->username;
            }

            throw new HueException('Unexpected response from bridge');
        } catch (GuzzleException $e) {
            throw new HueException('Failed to register with bridge: ' . $e->getMessage(), 0, $e);
        }
    }

    public function request(string $method, string $endpoint, array $options = []): array
    {
        if (!$this->username) {
            throw new AuthenticationException('No username set. Please register first.');
        }

        $uri = "{$this->username}/{$endpoint}";
        $cacheKey = $method . '_' . $uri . '_' . md5(serialize($options));

        // Try cache for GET requests
        if ($method === 'GET' && $this->cache) {
            $cachedData = $this->cache->get('api', $cacheKey);
            if ($cachedData !== null) {
                return $cachedData;
            }
        }

        $operation = function () use ($method, $uri, $options) {
            $response = $this->httpClient->request($method, $uri, $options);
            $data = json_decode($response->getBody()->getContents(), true);

            if (is_array($data) && isset($data[0]['error'])) {
                throw new HueException($data[0]['error']['description']);
            }

            return $data;
        };

        try {
            $data = $this->retryHandler->execute($operation, "HTTP {$method} {$endpoint}");
            
            // Cache GET responses
            if ($method === 'GET' && $this->cache && $data) {
                $this->cache->set('api', $cacheKey, $data);
            }

            return $data;
        } catch (GuzzleException $e) {
            throw new HueException('Request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function lights(): Lights
    {
        if ($this->lights === null) {
            $this->lights = new Lights($this);
        }
        return $this->lights;
    }

    public function groups(): Groups
    {
        if ($this->groups === null) {
            $this->groups = new Groups($this);
        }
        return $this->groups;
    }

    public function scenes(): Scenes
    {
        if ($this->scenes === null) {
            $this->scenes = new Scenes($this);
        }
        return $this->scenes;
    }

    public function schedules(): Schedules
    {
        if ($this->schedules === null) {
            $this->schedules = new Schedules($this);
        }
        return $this->schedules;
    }

    public function events(): Events
    {
        if ($this->events === null) {
            $this->events = new Events($this);
        }
        return $this->events;
    }

    public function sensors(): Sensors
    {
        if ($this->sensors === null) {
            $this->sensors = new Sensors($this);
        }
        return $this->sensors;
    }

    public function getBridgeIp(): string
    {
        return $this->bridgeIp;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getConfig(): array
    {
        return $this->request('GET', 'config');
    }

    public function getFullState(): array
    {
        return $this->request('GET', '');
    }

    public function isConnected(): bool
    {
        try {
            $this->getConfig();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getCache(): ?HueCache
    {
        return $this->cache;
    }

    public function getRetryHandler(): RetryHandler
    {
        return $this->retryHandler;
    }

    public function clearCache(): bool
    {
        if ($this->cache) {
            return $this->cache->clear();
        }
        return false;
    }

    public function getConnectionInfo(): array
    {
        return [
            'bridge_ip' => $this->bridgeIp,
            'username' => $this->username ? substr($this->username, 0, 8) . '...' : null,
            'connected' => $this->isConnected(),
            'cache_enabled' => $this->cache !== null,
            'retry_enabled' => true,
            'config' => $this->config
        ];
    }

    public function getBridgeCapabilities(): array
    {
        try {
            $config = $this->getConfig();
            return [
                'lights_available' => $config['capabilities']['lights']['available'] ?? 0,
                'groups_available' => $config['capabilities']['groups']['available'] ?? 0,
                'scenes_available' => $config['capabilities']['scenes']['available'] ?? 0,
                'schedules_available' => $config['capabilities']['schedules']['available'] ?? 0,
                'sensors_available' => $config['capabilities']['sensors']['available'] ?? 0,
                'streaming_capable' => isset($config['capabilities']['streaming']),
                'entertainment_capable' => isset($config['capabilities']['entertainment'])
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getResourceCounts(): array
    {
        try {
            return [
                'lights' => count($this->lights()->getAll()),
                'groups' => count($this->groups()->getAll()),
                'scenes' => count($this->scenes()->getAll()),
                'schedules' => count($this->schedules()->getAll()),
                'sensors' => count($this->sensors()->getAll())
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
}