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
use OguzhanTogay\HueClient\Resources\Sensors;
use OguzhanTogay\HueClient\Retry\RetryHandler;
use OguzhanTogay\HueClient\Signal\Signaler;
use OguzhanTogay\HueClient\V2\HueV2Client;
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
    private ?HueCache $cache = null;

    private ?Lights $lights = null;
    private ?Groups $groups = null;
    private ?Scenes $scenes = null;
    private ?Schedules $schedules = null;
    private ?Sensors $sensors = null;
    private ?Signaler $signaler = null;

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
            'connect_timeout' => 3,
            'verify' => false,
            'http_errors' => false,
            'retry_attempts' => 3,
            'cache_enabled' => true,
            'cache_type' => 'filesystem'
        ], $config);

        $this->httpClient = new Client([
            'base_uri' => "https://{$bridgeIp}/api/",
            'timeout' => $this->config['timeout'],
            'connect_timeout' => $this->config['connect_timeout'],
            'verify' => $this->config['verify'],
            'http_errors' => $this->config['http_errors'],
        ] + self::pinOptions($this->config['pinned_public_key'] ?? null));

        $this->retryHandler = new RetryHandler(
            $this->config['retry_attempts'],
            [1, 2, 4],
            $this->logger,
            fn (string $message): string => $this->redact($message)
        );

        if ($this->config['cache_enabled'] && class_exists('\Symfony\Component\Cache\Adapter\FilesystemAdapter')) {
            $this->cache = new HueCache(
                $this->config['cache_type'],
                $config,
                $this->logger
            );
        }
    }

    public function register(string $appName, ?string $deviceName = null): string
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
                    'bridge_ip' => $this->bridgeIp
                ]);
                return $this->username;
            }

            throw new HueException('Unexpected response from bridge');
        } catch (GuzzleException $e) {
            throw new HueException('Failed to register with bridge: ' . $this->redact($e->getMessage()));
        }
    }

    /**
     * @param array $options Guzzle request options, plus 'cache' => false to
     *                       skip the read cache for this request
     */
    public function request(string $method, string $endpoint, array $options = []): array
    {
        if (!$this->username) {
            throw new AuthenticationException('No username set. Please register first.');
        }

        $useCache = $options['cache'] ?? true;
        unset($options['cache']);

        $uri = "{$this->username}/{$endpoint}";
        $cacheKey = $method . '_' . $uri . '_' . md5(serialize($options));

        // Try cache for GET requests
        if ($method === 'GET' && $this->cache && $useCache) {
            $cachedData = $this->cache->get('api', $cacheKey);
            if ($cachedData !== null) {
                return $cachedData;
            }
        }

        $operation = function () use ($method, $uri, $options) {
            $response = $this->httpClient->request($method, $uri, $options);
            $data = json_decode($response->getBody()->getContents(), true);

            // The bridge answers a multi-field write with one entry per field;
            // any of them can be the error.
            if (is_array($data)) {
                $errors = [];
                foreach ($data as $item) {
                    if (is_array($item) && isset($item['error']['description'])) {
                        $errors[] = $item['error']['description'];
                    }
                }
                if ($errors !== []) {
                    throw new HueException(implode('; ', $errors));
                }
            }

            return $data;
        };

        try {
            // A POST that timed out may still have been applied by the bridge;
            // repeating it would create the group, scene or schedule twice.
            $data = $method === 'POST'
                ? $operation()
                : $this->retryHandler->execute($operation, "HTTP {$method} {$endpoint}");

            if ($this->cache) {
                if ($method === 'GET') {
                    if ($data) {
                        $this->cache->set('api', $cacheKey, $data);
                    }
                } else {
                    // A write makes every cached read stale.
                    $this->cache->invalidate('api');
                }
            }

            return $data;
        } catch (GuzzleException $e) {
            // Guzzle puts the full URI, token included, into its messages.
            throw new HueException('Request failed: ' . $this->redact($e->getMessage()));
        }
    }

    /**
     * Guzzle options that make curl refuse any other public key than the
     * pinned one (see Security\BridgeCertificate). Works with 'verify' => false.
     */
    public static function pinOptions(?string $pin): array
    {
        if ($pin === null || $pin === '') {
            return [];
        }

        if (!str_starts_with($pin, 'sha256//')) {
            throw new \InvalidArgumentException("pinned_public_key must look like 'sha256//<base64>'.");
        }

        return ['curl' => [CURLOPT_PINNEDPUBLICKEY => $pin]];
    }

    /**
     * Removes the bridge username (the API token) from a message.
     */
    public function redact(string $message): string
    {
        if (!$this->username) {
            return $message;
        }

        return str_replace($this->username, '***', $message);
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

    /**
     * Developer-notification states (attention, success, error, ...) on the
     * notifier's lamps or the lights you name. See docs/signals.md.
     */
    public function signal(): Signaler
    {
        if ($this->signaler === null) {
            $this->signaler = new Signaler($this);
        }
        return $this->signaler;
    }

    /**
     * The same bridge through its CLIP v2 API: real-time events, effects,
     * gradients, scenes by room. The application key is this client's username.
     */
    public function v2(array $options = []): HueV2Client
    {
        if (!$this->username) {
            throw new AuthenticationException('No username set. Please register first.');
        }

        return new HueV2Client($this->bridgeIp, $this->username, $options + [
            'verify' => $this->config['verify'],
            'pinned_public_key' => $this->config['pinned_public_key'] ?? null,
        ]);
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
