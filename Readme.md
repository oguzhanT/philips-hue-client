# Philips Hue Client for PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/oguzhanT/philips-hue-client.svg?style=flat-square)](https://packagist.org/packages/oguzhantogay/philips-hue-client)
[![Total Downloads](https://img.shields.io/packagist/dt/oguzhanT/philips-hue-client.svg?style=flat-square)](https://packagist.org/packages/oguzhantogay/philips-hue-client)
[![Monthly Downloads](https://img.shields.io/packagist/dm/oguzhanT/philips-hue-client.svg?style=flat-square)](https://packagist.org/packages/oguzhantogay/philips-hue-client)
[![PHP Version](https://img.shields.io/packagist/php-v/oguzhanT/philips-hue-client.svg?style=flat-square)](https://packagist.org/packages/oguzhantogay/philips-hue-client)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![GitHub Stars](https://img.shields.io/github/stars/oguzhanT/philips-hue-client?style=flat-square)](https://github.com/oguzhanT/philips-hue-client/stargazers)
[![GitHub Forks](https://img.shields.io/github/forks/oguzhanT/philips-hue-client?style=flat-square)](https://github.com/oguzhanT/philips-hue-client/network)
[![GitHub Issues](https://img.shields.io/github/issues/oguzhanT/philips-hue-client?style=flat-square)](https://github.com/oguzhanT/philips-hue-client/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/oguzhanT/philips-hue-client?style=flat-square)](https://github.com/oguzhanT/philips-hue-client/pulls)
[![Build Status](https://img.shields.io/github/actions/workflow/status/oguzhanT/philips-hue-client/tests.yml?style=flat-square)](https://github.com/oguzhanT/philips-hue-client/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/oguzhanT/philips-hue-client?style=flat-square)](https://codecov.io/gh/oguzhanT/philips-hue-client)
[![Maintainability](https://img.shields.io/codeclimate/maintainability/oguzhanT/philips-hue-client?style=flat-square)](https://codeclimate.com/github/oguzhanT/philips-hue-client)
[![Latest Stable Version](https://poser.pugx.org/oguzhanT/philips-hue-client/v/stable?format=flat-square)](https://packagist.org/packages/oguzhantogay/philips-hue-client)
[![Composer Dependencies](https://img.shields.io/badge/dependencies-up%20to%20date-brightgreen?style=flat-square)](https://packagist.org/packages/oguzhantogay/philips-hue-client)

A modern, fully-featured PHP client for Philips Hue smart lights. Control lights, rooms, scenes, and schedules with an elegant API. Framework-agnostic with built-in support for Laravel, Symfony, and standalone PHP applications.

## ✨ Features

- 🎨 **Complete Hue API v2 Support** - Lights, groups, scenes, schedules, sensors
- 🔍 **Auto Bridge Discovery** - Automatic bridge detection using mDNS/N-UPnP
- 💡 **Intuitive API** - Fluent interface for natural command chaining
- 🎬 **Scene Management** - Create, modify, and activate scenes
- 🎭 **Built-in Effects** - Color loops, breathing, alerts, and custom animations
- ⚡ **Event Streaming** - Real-time updates via Server-Sent Events (SSE)
- 🛠️ **CLI Tool** - Command-line interface for quick control
- 🌐 **REST API Server** - Full REST API with Swagger documentation
- 📦 **Framework Integration** - Ready-made adapters for popular frameworks
- 🧪 **Fully Tested** - Comprehensive test coverage with mocked responses
- 📊 **Resource Monitoring** - Track energy usage and light statistics
- 🔒 **Secure** - Supports Hue's enhanced security mode
- 🚀 **Performance** - Connection pooling, caching, and retry mechanisms
- 🐳 **Docker Ready** - Container support for easy deployment
- 🔄 **Rate Limiting** - Prevents API abuse and protects bridge
- 💾 **Smart Caching** - Automatic caching with configurable TTL

## 📋 Requirements

- PHP 8.0 or higher
- Philips Hue Bridge (v2 or newer)
- Network access to Hue Bridge
- ext-json
- ext-curl (optional, for better performance)

## 🚀 Installation

Install via Composer:

```bash
composer require oguzhant/philips-hue-client
```

## 🔧 Quick Start

### Bridge Discovery & Authentication

```php
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;

// Auto-discover bridges on your network
$discovery = new BridgeDiscovery();
$bridges = $discovery->discover();

if (empty($bridges)) {
    die("No Hue bridges found on network");
}

$bridge = $bridges[0]; // Use first bridge
echo "Found bridge: {$bridge->getId()} at {$bridge->getIp()}\n";

// Create client and authenticate
$client = new HueClient($bridge->getIp());

// First time setup - press bridge button then run:
$username = $client->register('my-app-name', 'my-device-name');
echo "Save this username: {$username}\n";

// For subsequent connections:
$client = new HueClient($bridgeIp, $username);
```

### Basic Light Control

```php
// Get all lights
$lights = $client->lights()->getAll();

foreach ($lights as $light) {
    echo "{$light->getName()}: {$light->getState()->getStatus()}\n";
}

// Control specific light
$light = $client->lights()->get(1);

// Simple commands
$light->on();
$light->off();
$light->toggle();

// Set properties
$light->setBrightness(75);  // 0-100%
$light->setColor('#FF5733'); // Hex color
$light->setColorTemperature(2700); // Kelvin (2000-6500)

// Chain commands
$light->on()
     ->setBrightness(100)
     ->setColor('#00FF00')
     ->transition(1000); // 1 second transition
```

### Room/Group Control

```php
// Get all rooms
$rooms = $client->groups()->getRooms();

// Control entire room
$livingRoom = $client->groups()->getByName('Living Room');
$livingRoom->on();
$livingRoom->setBrightness(60);
$livingRoom->setScene('Relax');

// Create custom group
$group = $client->groups()->create('Movie Lights', [1, 3, 5]);
$group->setColor('#0000FF')->dim(20);

// Control all lights
$client->groups()->all()->off();
```

### Scenes

```php
// List available scenes
$scenes = $client->scenes()->getAll();

foreach ($scenes as $scene) {
    echo "{$scene->getName()} - Room: {$scene->getGroup()}\n";
}

// Activate scene
$client->scenes()->activate('Sunset');

// Create custom scene
$scene = $client->scenes()->create(
    name: 'Movie Time',
    lights: [
        1 => ['on' => true, 'brightness' => 30, 'color' => '#0000FF'],
        2 => ['on' => false],
        3 => ['on' => true, 'brightness' => 20, 'color' => '#FF0000']
    ]
);
```

### Advanced Effects

```php
use OguzhanTogay\HueClient\Effects\ColorLoop;
use OguzhanTogay\HueClient\Effects\Breathing;
use OguzhanTogay\HueClient\Effects\Alert;

// Color loop effect
$effect = new ColorLoop($client);
$effect->start($light, duration: 30); // 30 seconds

// Breathing effect
$breathing = new Breathing($client);
$breathing->start($light, '#FF0000', speed: 'slow');

// Alert flash
$alert = new Alert($client);
$alert->flash($light, times: 3);

// Custom animation
$light->animate([
    ['color' => '#FF0000', 'duration' => 1000],
    ['color' => '#00FF00', 'duration' => 1000],
    ['color' => '#0000FF', 'duration' => 1000],
], repeat: 5);
```

### Schedules

```php
// Create schedule
$schedule = $client->schedules()->create(
    name: 'Morning Wake Up',
    command: $client->groups()->getByName('Bedroom')->sunrise(duration: 900),
    time: '07:00:00',
    repeat: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
);

// One-time schedule
$client->schedules()->once(
    name: 'Party Lights',
    command: $client->groups()->all()->party(),
    dateTime: '2024-12-31 23:00:00'
);

// Sunset/sunrise schedules
$client->schedules()->atSunset(
    command: $client->groups()->getByName('Garden')->on()
);
```

### Real-time Events (SSE)

```php
// Listen for light state changes
$client->events()->listen(function($event) {
    if ($event->getType() === 'light.state_changed') {
        echo "Light {$event->getLightId()} changed\n";
        echo "New state: " . json_encode($event->getData()) . "\n";
    }
});

// Subscribe to specific events
$client->events()->subscribe('motion.detected', function($event) {
    $client->groups()->getByName('Hallway')->on();
});
```

## 🛠️ CLI Usage

The package includes a powerful CLI tool:

```bash
# Install globally
composer global require oguzhant/philips-hue-client

# Discover bridges
hue discover

# Setup/authenticate
hue setup

# Light control
hue lights                    # List all lights
hue on "Living Room"         # Turn on light
hue off --all               # Turn off all lights
hue brightness 75 "Kitchen"  # Set brightness
hue color "#FF5733" --all   # Set color for all

# Scenes
hue scenes                   # List scenes
hue scene activate "Relax"   # Activate scene

# Effects
hue effect party --duration=30
hue effect sunrise --room="Bedroom" --duration=600

# Interactive mode
hue interactive              # Enter interactive shell
hue server                   # Start REST API server
```

## 🌐 REST API Server

The package includes a full REST API server with Swagger documentation:

### Quick Start

```bash
# Start the API server
./bin/hue-server --discover

# Or with specific bridge
./bin/hue-server -h 192.168.1.100 -u your-username

# Or using Composer
composer serve

# Or using Docker
docker-compose up
```

The API will be available at:
- **API Base URL**: `http://localhost:8080/api`
- **Swagger Documentation**: `http://localhost:8080/docs`
- **Health Check**: `http://localhost:8080/api/health`

### API Endpoints

#### Bridge Management
- `GET /api/health` - Bridge connectivity status
- `GET /api/bridge/info` - Bridge information
- `GET /api/bridge/config` - Bridge configuration

#### Lights
- `GET /api/lights` - List all lights
- `GET /api/lights/{id}` - Get specific light
- `PUT /api/lights/{id}/state` - Set light state
- `PATCH /api/lights/{id}/state` - Update light state partially
- `PUT /api/lights/{id}/name` - Rename light

#### Groups & Rooms
- `GET /api/groups` - List all groups
- `GET /api/rooms` - List all rooms
- `GET /api/zones` - List all zones
- `GET /api/groups/{id}` - Get specific group
- `POST /api/groups` - Create new group
- `PUT /api/groups/{id}/action` - Control group
- `DELETE /api/groups/{id}` - Delete group

#### Scenes
- `GET /api/scenes` - List all scenes
- `GET /api/scenes/{id}` - Get specific scene
- `POST /api/scenes` - Create new scene
- `PUT /api/scenes/{id}/activate` - Activate scene
- `DELETE /api/scenes/{id}` - Delete scene

#### Schedules
- `GET /api/schedules` - List all schedules
- `GET /api/schedules/{id}` - Get specific schedule
- `POST /api/schedules` - Create new schedule
- `PUT /api/schedules/{id}` - Update schedule
- `DELETE /api/schedules/{id}` - Delete schedule

#### Sensors
- `GET /api/sensors` - List all sensors
- `GET /api/sensors/{id}` - Get specific sensor
- `PUT /api/sensors/{id}/state` - Update sensor state

### Example API Usage

```bash
# Get all lights
curl http://localhost:8080/api/lights

# Turn on a light
curl -X PUT http://localhost:8080/api/lights/1/state \
  -H "Content-Type: application/json" \
  -d '{"on": true, "brightness": 75}'

# Set light color
curl -X PATCH http://localhost:8080/api/lights/1/state \
  -H "Content-Type: application/json" \
  -d '{"color": "#FF5733"}'

# Control a room
curl -X PUT http://localhost:8080/api/groups/1/action \
  -H "Content-Type: application/json" \
  -d '{"on": true, "brightness": 80, "color": "#00FF00"}'

# Activate a scene
curl -X PUT http://localhost:8080/api/scenes/abc123/activate

# Health check
curl http://localhost:8080/api/health
```

### Environment Configuration

Create a `.env` file (copy from `.env.example`):

```env
HUE_BRIDGE_IP=192.168.1.100
HUE_USERNAME=your-hue-username
HUE_PORT=8080
CACHE_TTL=300
```

## 🔌 Framework Integration

### 🟠 Laravel Integration

#### Installation & Setup

```bash
# Install the package
composer require oguzhant/philips-hue-client

# Publish configuration
php artisan vendor:publish --tag=hue-config

# Discover bridges
php artisan hue:discover

# Setup bridge authentication
php artisan hue:setup --discover
```

#### Configuration

```php
// config/hue.php (auto-published)
return [
    'default' => 'main',
    'bridges' => [
        'main' => [
            'ip' => env('HUE_BRIDGE_IP'),
            'username' => env('HUE_USERNAME'),
            'options' => [
                'timeout' => env('HUE_TIMEOUT', 5),
                'cache_enabled' => env('HUE_CACHE_ENABLED', true),
                'retry_attempts' => env('HUE_RETRY_ATTEMPTS', 3),
            ]
        ],
        // Multiple bridges supported
        'office' => [
            'ip' => env('HUE_BRIDGE_IP_2'),
            'username' => env('HUE_USERNAME_2'),
        ]
    ],
    'auto_discovery' => env('HUE_AUTO_DISCOVERY', true),
];
```

#### Environment Variables

```env
# .env
HUE_BRIDGE_IP=192.168.1.100
HUE_USERNAME=your-bridge-username
HUE_CACHE_ENABLED=true
HUE_CACHE_TYPE=redis
HUE_RETRY_ATTEMPTS=3
HUE_TIMEOUT=5
```

#### Service Provider Registration

```php
// config/app.php
'providers' => [
    // ...
    OguzhanTogay\HueClient\Laravel\HueServiceProvider::class,
],

'aliases' => [
    // ...
    'Hue' => OguzhanTogay\HueClient\Laravel\Facades\Hue::class,
],
```

#### Usage Examples

```php
use OguzhanTogay\HueClient\Laravel\Facades\Hue;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\ConnectionPool;

// Using Facade
Hue::lights()->getAll();
Hue::groups()->getByName('Living Room')->on();
Hue::scenes()->activate('Movie Time');

// Using Service Container
$hue = app(HueClient::class);
$hue->lights()->get(1)->setColor('#FF5733');

// Multiple Bridges
$pool = app(ConnectionPool::class);
$results = $pool->broadcastToAll(function($client) {
    return $client->groups()->all()->off();
});

// In Controllers
class LightController extends Controller
{
    public function __construct(private HueClient $hue) {}

    public function toggleLight(int $lightId)
    {
        $light = $this->hue->lights()->get($lightId);
        $light->toggle();
        
        return response()->json([
            'success' => true,
            'light' => $light->getName(),
            'status' => $light->getState()->isOn() ? 'on' : 'off'
        ]);
    }
}

// Background Jobs
class MorningRoutineJob implements ShouldQueue
{
    public function handle(HueClient $hue): void
    {
        $bedroom = $hue->groups()->getByName('Bedroom');
        $bedroom->sunrise(600); // 10 minute sunrise
    }
}

// Event Listeners
class MotionDetectedListener
{
    public function handle(MotionDetected $event, HueClient $hue): void
    {
        $hue->groups()->getByName($event->room)->on();
    }
}
```

#### Artisan Commands

```bash
# Discover bridges
php artisan hue:discover

# Setup bridge authentication  
php artisan hue:setup --discover

# Start REST API server
php artisan hue:serve --port=8080

# Clear Hue cache
php artisan cache:clear --tags=hue
```

### 🟡 Symfony Integration

#### Installation & Setup

```bash
# Install the package
composer require oguzhant/philips-hue-client

# Discover bridges
bin/console hue:discover

# Setup bridge authentication
bin/console hue:setup --discover
```

#### Bundle Registration

```php
// config/bundles.php
return [
    // ...
    OguzhanTogay\HueClient\Symfony\HueBundle::class => ['all' => true],
];
```

#### Configuration

```yaml
# config/packages/hue.yaml
hue:
    default_bridge: main
    auto_discovery: true
    
    bridges:
        main:
            ip: '%env(HUE_BRIDGE_IP)%'
            username: '%env(HUE_USERNAME)%'
            options:
                timeout: '%env(int:HUE_TIMEOUT)%'
                cache_enabled: '%env(bool:HUE_CACHE_ENABLED)%'
                retry_attempts: '%env(int:HUE_RETRY_ATTEMPTS)%'
        
        office:
            ip: '%env(HUE_BRIDGE_IP_2)%'
            username: '%env(HUE_USERNAME_2)%'
    
    cache:
        adapter: redis
        ttl:
            lights: 10
            groups: 30
            scenes: 60
    
    api:
        enabled: true
        port: 8080
        rate_limit: 100
```

#### Environment Variables

```env
# .env
HUE_BRIDGE_IP=192.168.1.100
HUE_USERNAME=your-bridge-username
HUE_CACHE_ENABLED=true
HUE_CACHE_TYPE=redis
HUE_RETRY_ATTEMPTS=3
HUE_TIMEOUT=5
```

#### Service Usage

```php
// In Controllers
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\ConnectionPool;

class LightController extends AbstractController
{
    public function __construct(
        private HueClient $hueClient,
        private ConnectionPool $connectionPool
    ) {}

    #[Route('/lights', methods: ['GET'])]
    public function lights(): JsonResponse
    {
        $lights = $this->hueClient->lights()->getAll();
        
        return $this->json(array_map(function($light) {
            return [
                'id' => $light->getId(),
                'name' => $light->getName(),
                'state' => $light->getState()->toArray()
            ];
        }, $lights));
    }

    #[Route('/lights/{id}/toggle', methods: ['POST'])]
    public function toggleLight(int $id): JsonResponse
    {
        $light = $this->hueClient->lights()->get($id);
        $light->toggle();
        
        return $this->json([
            'success' => true,
            'status' => $light->getState()->isOn() ? 'on' : 'off'
        ]);
    }
}

// In Services
#[AsAlias('app.hue_service')]
class HueService
{
    public function __construct(private HueClient $hueClient) {}

    public function createMoodLighting(string $room, string $mood): void
    {
        $group = $this->hueClient->groups()->getByName($room);
        
        match($mood) {
            'relax' => $group->setColor('#FF8C00')->setBrightness(30),
            'focus' => $group->setColor('#FFFFFF')->setBrightness(90),
            'party' => $group->setColor('#FF00FF')->setBrightness(100),
            default => $group->on()
        };
    }
}

// Event Subscribers
class HueEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private HueClient $hueClient) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'app.user_arrived_home' => 'onUserArrivedHome',
            'app.bedtime' => 'onBedtime',
        ];
    }

    public function onUserArrivedHome(): void
    {
        $this->hueClient->scenes()->activate('Welcome Home');
    }

    public function onBedtime(): void
    {
        $this->hueClient->groups()->all()->sunset(300);
    }
}
```

#### Console Commands

```bash
# Discover bridges
bin/console hue:discover

# Setup bridge authentication
bin/console hue:setup --discover

# Start REST API server
bin/console hue:serve --port=8080

# Clear cache
bin/console cache:clear
```

### Standalone/Vanilla PHP

```php
require 'vendor/autoload.php';

$config = [
    'bridge_ip' => '192.168.1.100',
    'username' => 'your-username'
];

$hue = new \OguzhanTogay\HueClient\HueClient(
    $config['bridge_ip'], 
    $config['username']
);
```

## 🐳 Docker Development

### Quick Start with Docker

```bash
# Clone the repository
git clone https://github.com/oguzhanT/philips-hue-client.git
cd philips-hue-client

# Copy environment file
cp .env.example .env

# Edit .env with your bridge IP and username
nano .env

# Start with Docker Compose
docker-compose up -d

# View logs
docker-compose logs -f hue-api
```

### Development Environment

```bash
# Development environment with hot reload
docker-compose -f docker-compose.dev.yml up

# Run tests in container
docker-compose exec dev composer test

# Access shell
docker-compose exec dev sh
```

### One-Click Development

[![Open in Gitpod](https://gitpod.io/button/open-in-gitpod.svg)](https://gitpod.io/#https://github.com/oguzhanT/philips-hue-client)

[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?hide_repo_select=true&ref=main&repo=your-repo-id)

## 🧪 Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer analyse

# Run API tests
composer test -- tests/Api/

# Test with Docker
docker-compose exec dev composer test
```

## 🚀 Performance Features

### Connection Pooling
```php
use OguzhanTogay\HueClient\ConnectionPool;

$pool = new ConnectionPool();
$pool->addBridge('192.168.1.100', 'username1');
$pool->addBridge('192.168.1.101', 'username2');

// Health check all bridges
$health = $pool->healthCheck();

// Broadcast action to all bridges
$results = $pool->broadcastToAll(function($client) {
    return $client->groups()->all()->on();
});
```

### Caching & Retry
```php
$client = new HueClient($bridgeIp, $username, [
    'cache_enabled' => true,
    'cache_type' => 'redis', // or 'filesystem'
    'retry_attempts' => 5,
    'timeout' => 10
]);

// Automatic caching and retry on failures
$lights = $client->lights()->getAll(); // Cached for 10 seconds
```

### Rate Limiting
The REST API automatically rate limits requests to protect your bridge:
- **Limit**: 100 requests per minute per IP
- **Headers**: `X-RateLimit-Limit`, `X-RateLimit-Remaining`
- **Error**: HTTP 429 when exceeded

## 📊 Examples

Check the `/examples` directory for complete examples:

### Basic Examples
- `basic-control.php` - Simple light control
- `party-mode.php` - Multi-room party effects
- `rest-api-client.php` - REST API usage examples
- `working-example.php` - Complete working example
- `interactive-test.php` - Interactive testing

### Framework Integration Examples
- `laravel-controller.php` - Laravel controller integration
- `symfony-controller.php` - Symfony controller integration

### Creative & Advanced Examples
- `music-sync-party.php` - 🎵 Music-synchronized party lighting with beat detection
- `security-system.php` - 🔒 Home security integration with motion alerts
- `gaming-mood-lighting.php` - 🎮 Gaming lighting with health bars and achievements
- `weather-based-lighting.php` - 🌤️ Weather-responsive ambient lighting
- `biometric-health-integration.php` - 💊 Health monitoring with biometric data
- `smart-home-automation.php` - 🏠 Complete smart home automation hub

## 🌟 Community Showcase

**Built something awesome?** Share it with us!

| Project | Description | Author |
|---------|-------------|---------|
| [Hue DJ Controller](link) | Sync lights with DJ mixer | @username |
| [Smart Office Bot](link) | Slack bot for office lighting | @username |
| [Gaming Immersion](link) | React to game events | @username |

[➕ Add your project](https://github.com/oguzhanT/philips-hue-client/discussions/new?category=show-and-tell)

## 💬 Join the Discussion

- 🐛 [Report bugs](https://github.com/oguzhanT/philips-hue-client/issues/new?template=bug_report.md)
- 💡 [Request features](https://github.com/oguzhanT/philips-hue-client/issues/new?template=feature_request.md)  
- 💬 [General discussion](https://github.com/oguzhanT/philips-hue-client/discussions)
- 🆘 [Get help](https://github.com/oguzhanT/philips-hue-client/discussions/categories/q-a)

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🔗 Links

- [Philips Hue API Documentation](https://developers.meethue.com/)
- [Report Issues](https://github.com/oguzhanT/philips-hue-client/issues)
- [Packagist](https://packagist.org/packages/oguzhantogay/philips-hue-client)

## 💖 Support

If you find this package useful, please consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting new features
- 🍺 [Buying me a coffee](https://www.buymeacoffee.com/oguzhanT)

---

Made with ❤️ by [Oguzhan Togay](https://github.com/oguzhanT)