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
        $light->getState()->getStatus()
    );
}

// Control specific light
if (!empty($lights)) {
    $light = $lights[0]; // Use first light
    echo "\nControlling light: {$light->getName()}\n";
    
    // Turn on and set to warm white
    $light->on()
         ->setBrightness(75)
         ->setColorTemperature(2700)
         ->transition(1000); // 1 second transition
    
    echo "Light turned on with warm white at 75% brightness\n";
    
    sleep(3);
    
    // Change to blue
    $light->setColor('#0000FF')->transition(2000);
    echo "Changed to blue\n";
    
    sleep(3);
    
    // Turn off
    $light->off();
    echo "Light turned off\n";
}

// Control all lights in living room
$livingRoom = $client->groups()->getByName('Living Room');
if ($livingRoom) {
    echo "\nControlling Living Room\n";
    $livingRoom->on()->setBrightness(50)->setColor('#FF5733');
    echo "Living Room lights set to orange at 50%\n";
}