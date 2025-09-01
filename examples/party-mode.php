<?php

require_once __DIR__ . '/../vendor/autoload.php';

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Effects\ColorLoop;
use OguzhanTogay\HueClient\Effects\Breathing;

$client = new HueClient('192.168.1.100', 'your-username-here');

echo "Starting party mode!\n";

// Get all groups/rooms
$groups = $client->groups()->getAll();
$partyGroups = [];

foreach ($groups as $group) {
    if ($group->getId() !== 0 && $group->getType() === 'Room') {
        $partyGroups[] = $group;
    }
}

if (empty($partyGroups)) {
    // Fallback to all lights
    $partyGroups[] = $client->groups()->all();
}

echo "Party groups: " . count($partyGroups) . "\n";

// Start color loop on all party groups
$colorLoop = new ColorLoop($client);

foreach ($partyGroups as $group) {
    echo "Starting party effect in: {$group->getName()}\n";
    
    // Start with colorloop effect
    $group->on()->setBrightness(100);
    $colorLoop->start($group, 60); // 60 seconds
}

echo "Party mode activated for 60 seconds!\n";

// Alternative: Custom color sequence
sleep(65); // Wait for colorloop to finish

echo "\nStarting custom color sequence...\n";

$partyColors = [
    '#FF0000', // Red
    '#00FF00', // Green
    '#0000FF', // Blue
    '#FFFF00', // Yellow
    '#FF00FF', // Magenta
    '#00FFFF', // Cyan
    '#FF8000', // Orange
    '#8000FF', // Purple
];

foreach ($partyGroups as $group) {
    $colorLoop->startCustom($group, $partyColors, 1000, 3); // 1 second per color, 3 cycles
}

echo "Custom party sequence complete!\n";

// Cool down with breathing effect
sleep(2);

echo "Cooling down with breathing effect...\n";
$breathing = new Breathing($client);

foreach ($partyGroups as $group) {
    $breathing->start($group, '#6600FF', 'slow', 30); // Purple breathing for 30 seconds
}

echo "Party mode complete!\n";