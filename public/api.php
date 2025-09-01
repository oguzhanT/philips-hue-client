<?php

require_once __DIR__ . '/../vendor/autoload.php';

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Api\RestApi;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;

// Configuration
$config = [
    'bridge_ip' => $_ENV['HUE_BRIDGE_IP'] ?? null,
    'username' => $_ENV['HUE_USERNAME'] ?? null,
    'auto_discover' => $_ENV['HUE_AUTO_DISCOVER'] ?? true,
];

// Auto-discover bridge if not configured
if (!$config['bridge_ip'] && $config['auto_discover']) {
    echo "Auto-discovering Hue Bridge...\n";
    $discovery = new BridgeDiscovery();
    $bridges = $discovery->discover();
    
    if (!empty($bridges)) {
        $config['bridge_ip'] = $bridges[0]->getIp();
        echo "Found bridge at: {$config['bridge_ip']}\n";
    } else {
        die("No Hue bridges found. Please set HUE_BRIDGE_IP environment variable.\n");
    }
}

if (!$config['bridge_ip']) {
    die("Bridge IP not configured. Please set HUE_BRIDGE_IP environment variable.\n");
}

if (!$config['username']) {
    die("Username not configured. Please set HUE_USERNAME environment variable or use the registration endpoint.\n");
}

try {
    // Create Hue client
    $hueClient = new HueClient($config['bridge_ip'], $config['username']);
    
    // Test connection
    if (!$hueClient->isConnected()) {
        die("Cannot connect to Hue Bridge at {$config['bridge_ip']}\n");
    }
    
    // Create and run REST API
    $api = new RestApi($hueClient);
    $api->run();
    
} catch (Exception $e) {
    die("Error starting API server: " . $e->getMessage() . "\n");
}