# Lampsignal PHP

A PHP client for Philips Hue, and the core of [Lampsignal](https://github.com/oguzhanT/lampsignal): lamps that show what your tools are doing.

*Formerly `oguzhantogay/philips-hue-client`. An independent open-source project, not affiliated with or endorsed by Signify (Philips Hue) or Anthropic.*

[![Latest Version on Packagist](https://img.shields.io/packagist/v/oguzhantogay/lampsignal.svg?style=flat-square)](https://packagist.org/packages/oguzhantogay/lampsignal)
[![Total Downloads](https://img.shields.io/packagist/dt/oguzhantogay/lampsignal.svg?style=flat-square)](https://packagist.org/packages/oguzhantogay/lampsignal)
[![Monthly Downloads](https://img.shields.io/packagist/dm/oguzhantogay/lampsignal.svg?style=flat-square)](https://packagist.org/packages/oguzhantogay/lampsignal)
[![PHP Version](https://img.shields.io/packagist/php-v/oguzhantogay/lampsignal.svg?style=flat-square)](https://packagist.org/packages/oguzhantogay/lampsignal)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![GitHub Stars](https://img.shields.io/github/stars/oguzhanT/lampsignal-php?style=flat-square)](https://github.com/oguzhanT/lampsignal-php/stargazers)
[![GitHub Forks](https://img.shields.io/github/forks/oguzhanT/lampsignal-php?style=flat-square)](https://github.com/oguzhanT/lampsignal-php/network)
[![GitHub Issues](https://img.shields.io/github/issues/oguzhanT/lampsignal-php?style=flat-square)](https://github.com/oguzhanT/lampsignal-php/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/oguzhanT/lampsignal-php?style=flat-square)](https://github.com/oguzhanT/lampsignal-php/pulls)
[![Latest Stable Version](https://poser.pugx.org/oguzhantogay/lampsignal/v/stable?format=flat-square)](https://packagist.org/packages/oguzhantogay/lampsignal)
[![Composer Dependencies](https://img.shields.io/badge/dependencies-up%20to%20date-brightgreen?style=flat-square)](https://packagist.org/packages/oguzhantogay/lampsignal)

A PHP client for Philips Hue smart lights. Control lights, rooms, scenes, and schedules with a fluent API, from plain PHP, Laravel, or Symfony, or from the command line.

It speaks both bridge APIs: the classic **v1** API through `HueClient`, and **CLIP v2** (real-time events, effects, gamut-aware colors) through `HueV2Client` or `$client->v2()`.

## ✨ Features

- 💡 **Lights, groups, scenes, schedules, sensors** - Hue v1 API, with a fluent interface for chaining
- 🆕 **CLIP v2** - Lights, rooms, zones, scenes, devices; effects like candle and fire; colors fitted to each lamp's gamut
- ⚡ **Real-time events** - The bridge's v2 event stream, in PHP and as `hue events`
- 🔔 **Signals** - Lamps as build, deploy and agent notifications, shared with the Lampsignal plugin
- 🔍 **Bridge discovery** - Local mDNS (no internet needed), then Philips' discovery service
- 🎭 **Effects** - Color loop, breathing, alert flashes, and frame animations
- 🛠️ **CLI tool** - `hue` for quick control from the terminal
- 🌐 **REST API server** - HTTP endpoints over the client, for trusted local networks
- 📦 **Framework integration** - Laravel service provider, facade and commands; Symfony bundle
- 🔁 **Retries and connection pooling** - Retries transient failures; manages several bridges
- 💾 **Optional caching and rate limiting** - Turn on by installing `symfony/cache` and `symfony/rate-limiter`
- 🐳 **Docker** - Development setup with Docker Compose
- 🍎 **macOS menu bar app** - [Lampsignal for Mac](#-macos-menu-bar-app)

## 📋 Requirements

- PHP 8.2 or higher
- Philips Hue Bridge (the square v2 bridge)
- Network access to the bridge
- ext-json
- ext-sockets (optional, for local mDNS discovery)

## 🚀 Installation

Install via Composer:

```bash
composer require oguzhantogay/lampsignal
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

// Create custom scene. Light states use the bridge's own fields:
// bri 1-254, hue 0-65535, sat 0-254, ct in mireds (153-500).
$scene = $client->scenes()->create(
    name: 'Movie Time',
    lights: [
        1 => ['on' => true, 'bri' => 76, 'hue' => 46920, 'sat' => 254],  // dim blue
        2 => ['on' => false],
        3 => ['on' => true, 'bri' => 50, 'hue' => 0, 'sat' => 254],      // dim red
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

Schedules run on the bridge, so a command is the bridge request to make at that time:
`address` (the API path, including your username), `method`, and `body`.

```php
$bedroom = $client->groups()->getByName('Bedroom');
$command = [
    'address' => "/api/{$client->getUsername()}/groups/{$bedroom->getId()}/action",
    'method'  => 'PUT',
    'body'    => ['on' => true, 'bri' => 254, 'ct' => 250, 'transitiontime' => 9000], // 15-minute fade
];

// Weekdays at 07:00
$client->schedules()->create(
    name: 'Morning Wake Up',
    command: $command,
    time: '07:00:00',
    repeat: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
);

// Once
$client->schedules()->once('New Year', $command, '2026-12-31 23:00:00');

// At sunset (needs the daylight sensor configured on the bridge)
$client->schedules()->atSunset($command);
```

### CLIP v2: events, effects and rooms

```php
use OguzhanTogay\HueClient\V2\HueV2Client;

$hue = new HueV2Client($bridgeIp, $username);   // or $client->v2()

$desk = $hue->lights()->byName('Desk');
$desk->on()->color('#FF5733')->setBrightness(60);     // color fitted to the lamp's gamut
$desk->colorTemperature(2700, transitionMs: 2000);
$desk->effect('candle');                               // see $desk->effects()
$desk->identify();                                      // breathe once

$hue->rooms()->byName('Office')->groupedLight()->off(transitionMs: 1000);
$hue->scenes()->byName('Relax')->recall(dynamic: true);

// Real time: every change on the bridge as it happens
$hue->events()->listen(function (array $event) {
    foreach ($event['data'] as $change) {
        echo "{$event['type']} {$change['type']} {$change['id']}\n";
    }
    return true; // false stops listening
}, seconds: 60);
```

Other resource types (motion, temperature, button, ...) are available as `$hue->resources('motion')->all()`.

### Signals: lamps as developer notifications

Show a build, deploy or agent state on a lamp. The colors, the lamps and the pause switch are shared with the [Lampsignal plugin](https://github.com/oguzhanT/lampsignal) and [Lampsignal for Mac](#-macos-menu-bar-app); see [docs/signals.md](docs/signals.md).

```php
$client->signal()->send('success');                        // the notifier's lamps (light_ids)
$client->signal()->send('error', ['Desk', 3]);             // lights by name or id
$client->signal()->send('attention', 'Desk', restoreAfter: 10); // put the lamp back after 10 s

$client->signal()->pause(3600);   // quiet for an hour (the Lampsignal plugin too)
$client->signal()->resume();
```

Signals: `attention` (blue, blinking), `success` (green), `error` (red), `warning` (amber), `working` (dim blue), `off`. `send()` returns `false` and changes nothing while paused. `restoreAfter` waits in the foreground.

#### Laravel notifications

```php
use OguzhanTogay\HueClient\Laravel\Notifications\HueChannel;
use OguzhanTogay\HueClient\Laravel\Notifications\HueMessage;

class DeployFailed extends Notification implements ShouldQueue
{
    public function via($notifiable): array
    {
        return [HueChannel::class];
    }

    public function toHue($notifiable): HueMessage
    {
        return HueMessage::error()->lights('Desk')->restoreAfter(30);
    }
}

// Lights default to the notifiable's route, then to the notifier's lamps:
public function routeNotificationForHue($notification): array
{
    return ['Desk'];
}
```

Queue notifications that use `restoreAfter()`: it waits before restoring.

## 🛠️ CLI Usage

The package includes a powerful CLI tool:

```bash
# Install globally
composer global require oguzhantogay/lampsignal

# Discover bridges
hue discover

# Setup/authenticate (shares ~/.config/hue-notify/config.json with the Lampsignal plugin:
# already paired there? the CLI just works)
hue setup

# Lights, rooms, sensors, schedules
hue lights                       # List all lights
hue rooms                        # List rooms and groups
hue status                       # Bridge status
hue on "Living Room"             # Turn on a light or group
hue off --all                    # Turn off all lights
hue toggle --room="Kitchen"      # Toggle a room
hue brightness "Kitchen" -b 75   # Set brightness (0-100)
hue color --all -c "#FF5733"     # Set color for all

# Scenes
hue scenes                       # List scenes
hue scene activate               # Asks for the scene name

# Signals (see docs/signals.md)
hue signal success                    # notifier lamps
hue signal error --light=Desk --light=3 --restore=10
hue pause 60                          # minutes; no argument = until resume
hue resume

# Watch the bridge live (CLIP v2 event stream)
hue events
hue events --duration=60

# Pin the bridge certificate (setup does this since 2.1)
hue pin

# Effects (they run in the foreground for --duration seconds)
hue effect party --duration=30
hue effect breathing --room="Bedroom" --duration=60
hue effect sunrise --room="Bedroom" --duration=600
```

## 🌐 REST API Server

The package includes a REST API over the client. Every request needs an API key, except `/api/health` and the docs.

### Quick Start

```bash
# Pick a key once and give it to your clients. Pass secrets through the
# environment: command-line options are visible to every user in `ps`.
export HUE_API_KEY=$(openssl rand -hex 24) HUE_USERNAME=your-username

# Listens on 127.0.0.1:8080 by default; add --bind 0.0.0.0 to serve your network
./vendor/bin/hue-server -h 192.168.1.100

# Or, with the bridge already set up through `hue setup`
hue server

# Or using Docker (reads HUE_BRIDGE_IP, HUE_USERNAME and HUE_API_KEY from the environment)
docker compose up
```

Clients send the key as `Authorization: Bearer <key>` or `X-API-Key: <key>`.

| Setting | Option | Environment | Default |
|---|---|---|---|
| API key | `-k`, `--api-key` | `HUE_API_KEY` | none: requests are refused |
| Run without a key | `--insecure` | `HUE_API_INSECURE=1` | off. Trusted networks only |
| Listen address | `-b`, `--bind` | `HUE_BIND` | `127.0.0.1` |
| Port | `-p`, `--port` | `HUE_PORT` | `8080` |
| Browser origins allowed (CORS) | `--cors` | `HUE_CORS_ORIGINS` (comma separated) | none |
| Error details in responses | | `HUE_DEBUG=1` | off |

Writes (`POST`, `PUT`, `PATCH`) must be sent as `application/json`. That stops web pages from posting to the API behind your back.

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

### Webhooks: CI and errors on your lamps

The REST server can turn GitHub and Sentry webhooks into [signals](#signals-lamps-as-developer-notifications). These endpoints need no API key; every delivery must carry a valid signature for its secret, and an endpoint without a secret is off.

| Endpoint | Secret | Signals |
|---|---|---|
| `POST /hooks/github` | `HUE_GITHUB_WEBHOOK_SECRET` | `workflow_run` and `check_suite`: running → working, success → success, failure → error, cancelled → warning. `deployment_status`: pending → working, success → success, failure → error |
| `POST /hooks/sentry` | `HUE_SENTRY_WEBHOOK_SECRET` (the integration's client secret) | new or unresolved issue → error, resolved → success; metric alert critical/warning/resolved → error/warning/success |

- `HUE_WEBHOOK_LIGHTS`: lamps to light, comma separated names or ids (default: the notifier's lamps).
- `HUE_GITHUB_BRANCHES`: only these branches, e.g. `main` (default: all).
- In GitHub, set the content type to `application/json`; form-encoded deliveries are refused with 415.
- Workflow runs from forks are ignored, even on a branch called `main`.
- Each signed delivery is accepted once per day (keyed by a hash of its signed body, since delivery-id headers are not signed), so captured deliveries cannot be replayed. Redelivering from GitHub's UI repeats the body and is ignored too. The state lives in `HUE_WEBHOOK_STATE_DIR` (default: `~/.cache/lampsignal`).
- The bridge is on your LAN and GitHub and Sentry are on the internet, so expose only `/hooks/` through a tunnel (Cloudflare Tunnel, ngrok, Tailscale Funnel).

### Example API Usage

```bash
# Get all lights
curl -H "Authorization: Bearer $HUE_API_KEY" http://localhost:8080/api/lights

# Turn on a light (PUT takes bridge units: brightness 0-254, hue 0-65535, saturation 0-254)
curl -X PUT http://localhost:8080/api/lights/1/state \
  -H "Authorization: Bearer $HUE_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"on": true, "brightness": 190}'

# Set light color (PATCH takes brightness 0-100, color as hex, temperature in Kelvin)
curl -X PATCH http://localhost:8080/api/lights/1/state \
  -H "Authorization: Bearer $HUE_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"color": "#FF5733"}'

# Control a room
curl -X PUT http://localhost:8080/api/groups/1/action \
  -H "Authorization: Bearer $HUE_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"on": true, "brightness": 80, "color": "#00FF00"}'

# Activate a scene
curl -X PUT -H "Authorization: Bearer $HUE_API_KEY" http://localhost:8080/api/scenes/abc123/activate

# Health check
curl http://localhost:8080/api/health
```

### Environment Configuration

Create a `.env` file (copy from `.env.example`):

```env
HUE_BRIDGE_IP=192.168.1.100
HUE_USERNAME=your-hue-username
HUE_PORT=8080
HUE_API_KEY=your-generated-key
HUE_CORS_ORIGINS=
```

## 🔌 Framework Integration

### 🟠 Laravel Integration

#### Installation & Setup

```bash
# Install the package
composer require oguzhantogay/lampsignal

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
composer require oguzhantogay/lampsignal

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

## 🍎 macOS Menu Bar App

[`macos/Lampsignal`](macos/Lampsignal) is a small SwiftUI menu bar app: switch lights and rooms, set brightness and color, and pause or retarget the [Lampsignal plugin](https://github.com/oguzhanT/lampsignal) notifier. Build it with `macos/Lampsignal/build-app.sh --install`. It is not part of the Composer package.

<img src="macos/Lampsignal/docs/menu-bar.png" alt="Lampsignal for Mac" width="356">

## 🚧 Known limitations and roadmap

- **Effects block** the PHP process while they run (the v1 `Effects` classes). The v2 `effect()` runs on the bridge instead.
- **Caching and rate limiting** are off unless `symfony/cache` and `symfony/rate-limiter` are installed.
- **TLS**: the bridge's certificate is signed by Philips' own CA. Pin it (see [Pinning the bridge certificate](#pinning-the-bridge-certificate)); without a pin it is not checked.
- **Entertainment areas** (DTLS streaming) are not supported.

Want Claude to control the lights? The [Lampsignal plugin](https://github.com/oguzhanT/lampsignal) ships an MCP server.

See [CHANGELOG.md](CHANGELOG.md) for what changed in each release.

## 🐳 Docker Development

### Quick Start with Docker

```bash
# Clone the repository
git clone https://github.com/oguzhanT/lampsignal-php.git
cd lampsignal-php

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

### Pinning the bridge certificate

The bridge's certificate is signed by Philips' own CA, so it cannot be verified the usual way. Pin it instead: read it once on a network you trust, check that it belongs to your bridge, and every later request refuses any other key, so a spoofed discovery answer or a man-in-the-middle cannot collect your token.

```php
use OguzhanTogay\HueClient\Security\BridgeCertificate;

$certificate = BridgeCertificate::fetch($bridgeIp);
if (!$certificate->matchesBridgeId($bridge->getId())) {
    throw new RuntimeException('Not the bridge discovery reported');
}

$client = new HueClient($bridgeIp, $username, ['pinned_public_key' => $certificate->pin]);
$client->v2();   // inherits the pin
```

`hue setup` pins automatically and refuses a certificate that is not for the bridge it discovered; `hue pin` adds a pin to a setup made before 2.1 (it shows the certificate's bridge id for you to check, it does not compare it). The pin also covers `$hue->events()`: the event stream is tied to the pinned certificate before any header is sent. For Laravel, set `HUE_PINNED_PUBLIC_KEY`.

> Pair and pin on a network you trust. Discovery answers are not authenticated, so a device that answers in place of your bridge during that first step can present its own certificate with your bridge's id.

### Caching & Retry

Caching needs `symfony/cache` (and `ext-redis` for the Redis adapter): `composer require symfony/cache`.

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
With `symfony/rate-limiter` installed, the REST API rate limits requests to protect your bridge:
- **Limit**: 100 requests per minute per client address (`REMOTE_ADDR`; forwarding headers are ignored because clients can forge them), counted before the API key check so key guessing is slowed too
- **Storage**: the counts persist across requests only with `symfony/cache` installed; PHP's built-in server forgets in-memory counts after each request
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

| Project | Description |
|---------|-------------|
| [Lampsignal plugin](https://github.com/oguzhanT/lampsignal) | Claude Code plugin: your desk lamp turns blue when Claude needs you, green when it is done |
| [Lampsignal for Mac](macos/Lampsignal) | macOS menu bar app for lights, rooms and the notifier |

**Built something with it?** Share it:

[➕ Add your project](https://github.com/oguzhanT/lampsignal-php/discussions/new?category=show-and-tell)

## 💬 Join the Discussion

- 🐛 [Report bugs](https://github.com/oguzhanT/lampsignal-php/issues/new?template=bug_report.md)
- 💡 [Request features](https://github.com/oguzhanT/lampsignal-php/issues/new?template=feature_request.md)  
- 💬 [General discussion](https://github.com/oguzhanT/lampsignal-php/discussions)
- 🆘 [Get help](https://github.com/oguzhanT/lampsignal-php/discussions/categories/q-a)

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🔗 Links

- [Philips Hue API Documentation](https://developers.meethue.com/)
- [Report Issues](https://github.com/oguzhanT/lampsignal-php/issues)
- [Packagist](https://packagist.org/packages/oguzhantogay/lampsignal)

## 💖 Support

If you find this package useful, please consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting new features
- 🍺 [Buying me a coffee](https://www.buymeacoffee.com/oguzhanT)

---

Made with ❤️ by [Oguzhan Togay](https://github.com/oguzhanT)
