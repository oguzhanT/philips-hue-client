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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class HueClient
{
    private Client $httpClient;
    private ?string $username;
    private string $bridgeIp;
    private LoggerInterface $logger;
    private array $config;
    
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
        ], $config);

        $this->httpClient = new Client([
            'base_uri' => "https://{$bridgeIp}/api/",
            'timeout' => $this->config['timeout'],
            'verify' => $this->config['verify'],
            'http_errors' => $this->config['http_errors'],
        ]);
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

        try {
            $response = $this->httpClient->request($method, $uri, $options);
            $data = json_decode($response->getBody()->getContents(), true);

            if (is_array($data) && isset($data[0]['error'])) {
                throw new HueException($data[0]['error']['description']);
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
}