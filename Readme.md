# Philips Hue Client for PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/oguzhantogay/philips-hue-client.svg?style=flat-square)](https://packagist.org/packages/oguzhantogay/philips-hue-client)
[![Total Downloads](https://img.shields.io/packagist/dt/oguzhantogay/philips-hue-client.svg?style=flat-square)](https://packagist.org/packages/oguzhantogay/philips-hue-client)
[![PHP Version](https://img.shields.io/packagist/php-v/oguzhantogay/philips-hue-client.svg?style=flat-square)](https://packagist.org/packages/oguzhantogay/philips-hue-client)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

A modern, fully-featured PHP client for Philips Hue smart lights. Control lights, rooms, scenes, and schedules with an elegant API. Framework-agnostic with built-in support for Laravel, Symfony, and standalone PHP applications.

## ✨ Features

- 🎨 **Complete Hue API v2 Support** - Lights, groups, scenes, schedules, sensors
- 🔍 **Auto Bridge Discovery** - Automatic bridge detection using mDNS/N-UPnP
- 💡 **Intuitive API** - Fluent interface for natural command chaining
- 🎬 **Scene Management** - Create, modify, and activate scenes
- 🎭 **Built-in Effects** - Color loops, breathing, alerts, and custom animations
- ⚡ **Event Streaming** - Real-time updates via Server-Sent Events (SSE)
- 🛠️ **CLI Tool** - Command-line interface for quick control
- 📦 **Framework Integration** - Ready-made adapters for popular frameworks
- 🧪 **Fully Tested** - Comprehensive test coverage with mocked responses
- 📊 **Resource Monitoring** - Track energy usage and light statistics
- 🔒 **Secure** - Supports Hue's enhanced security mode

## 📋 Requirements

- PHP 8.0 or higher
- Philips Hue Bridge (v2 or newer)
- Network access to Hue Bridge
- ext-json
- ext-curl (optional, for better performance)

## 🚀 Installation

Install via Composer:

```bash
composer require oguzhantogay/philips-hue-client
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
composer global require oguzhantogay/philips-hue-client

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
```

## 🔌 Framework Integration

### Laravel

```php
// config/services.php
'hue' => [
    'bridge_ip' => env('HUE_BRIDGE_IP'),
    'username' => env('HUE_USERNAME'),
],

// AppServiceProvider.php
use OguzhanTogay\HueClient\Laravel\HueServiceProvider;

public function register()
{
    $this->app->register(HueServiceProvider::class);
}

// Usage
use OguzhanTogay\HueClient\Laravel\Facades\Hue;

Hue::light('Living Room')->on();
```

### Symfony

```yaml
# config/services.yaml
services:
    hue.client:
        class: OguzhanTogay\HueClient\HueClient
        arguments:
            - '%env(HUE_BRIDGE_IP)%'
            - '%env(HUE_USERNAME)%'
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

## 🧪 Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer analyse
```

## 📊 Examples

Check the `/examples` directory for complete examples:

- `basic-control.php` - Simple light control
- `party-mode.php` - Multi-room party effects
- `wake-up-routine.php` - Gradual wake-up with sunrise
- `motion-activated.php` - Motion sensor integration
- `energy-monitor.php` - Track energy usage
- `voice-control.php` - Integration with voice assistants

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🔗 Links

- [Philips Hue API Documentation](https://developers.meethue.com/)
- [Report Issues](https://github.com/oguzhantogay/philips-hue-client/issues)
- [Packagist](https://packagist.org/packages/oguzhantogay/philips-hue-client)

## 💖 Support

If you find this package useful, please consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting new features
- 🍺 [Buying me a coffee](https://www.buymeacoffee.com/oguzhantogay)

---

Made with ❤️ by [Oguzhan Togay](https://github.com/oguzhantogay)