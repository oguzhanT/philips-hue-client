<?php

require_once __DIR__ . '/../vendor/autoload.php';

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;

// Discover and connect to bridge
$discovery = new BridgeDiscovery();
$bridges = $discovery->discover();

if (empty($bridges)) {
    die("No Hue bridges found on network\n");
}

$bridge = $bridges[0];
echo "Found bridge: {$bridge->getId()} at {$bridge->getIp()}\n";

// For first time setup, uncomment the following lines:
// $client = new HueClient($bridge->getIp());
// $username = $client->register('basic-example', 'my-device');
// echo "Save this username: {$username}\n";

// For normal usage:
$username = 'your-saved-username-here';
$client = new HueClient($bridge->getIp(), $username);

// Get all lights
echo "\n=== All Lights ===\n";
$lights = $client->lights()->getAll();

foreach ($lights as $light) {
    echo sprintf("Light %d: %s (%s) - %s\n", 
        $light->getId(),
        $light->getName(),
        $light->getType(),
        $light->getState()->isOn() ? 'On' : 'Off'
    );
}

// Control specific light
if (!empty($lights)) {
    $light = $lights[0]; // Use first light
    echo "\nControlling light: {$light->getName()}\n";
    
    // Turn on and set brightness
    $light->on()->setBrightness(75);
    
    echo "Light turned on at 75% brightness\n";
    
    sleep(3);
    
    // Change to blue using XY coordinates
    $light->setState(['xy' => [0.167, 0.04]]);
    echo "Changed to blue\n";
    
    sleep(3);
    
    // Turn off
    $light->off();
    echo "Light turned off\n";
}

// Control groups
echo "\n=== Groups ===\n";
$groups = $client->groups()->getAll();
foreach ($groups as $group) {
    echo sprintf("Group %d: %s (%s)\n", 
        $group->getId(),
        $group->getName(),
        $group->getType()
    );
    
    // Control first group if exists
    if ($group->getId() == 1) {
        echo "Controlling group: " . $group->getName() . "\n";
        // Note: Group control methods would need to be implemented
        break;
    }
}