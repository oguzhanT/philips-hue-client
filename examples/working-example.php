<?php

require_once __DIR__ . '/../vendor/autoload.php';

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Exceptions\HueException;

// Example configuration - replace with your actual values
$bridgeIp = '192.168.0.101'; // Your bridge IP
$username = null; // Will be created if null

try {
    echo "Philips Hue Client - Working Example\n";
    echo "====================================\n\n";
    
    $client = new HueClient($bridgeIp, $username);
    
    // If no username, create one (requires button press)
    if (!$username) {
        echo "Creating new user...\n";
        echo "Press the button on your Hue Bridge and press Enter...";
        readline();
        
        try {
            $username = $client->register('working-example', 'example-device');
            echo "Username created: $username\n";
            echo "Save this username for future use!\n\n";
            
            // Update client with new username
            $client->setUsername($username);
            
        } catch (HueException $e) {
            echo "Error creating user: " . $e->getMessage() . "\n";
            echo "Make sure you pressed the bridge button!\n";
            exit(1);
        }
    }
    
    echo "Connected to bridge at $bridgeIp\n\n";
    
    // List all lights
    echo "=== LIGHTS ===\n";
    $lights = $client->lights()->getAll();
    
    if (empty($lights)) {
        echo "No lights found.\n";
    } else {
        foreach ($lights as $light) {
            $state = $light->getState();
            echo sprintf(
                "Light %d: %s (%s) - %s (Brightness: %d%%)\n",
                $light->getId(),
                $light->getName(),
                $light->getType(),
                $state->isOn() ? 'On' : 'Off',
                round(($state->getBrightness() ?? 0) / 254 * 100)
            );
        }
        
        // Control first light
        if (!empty($lights)) {
            $firstLight = $lights[array_key_first($lights)];
            echo "\nControlling light: " . $firstLight->getName() . "\n";
            
            // Turn on with 50% brightness
            $firstLight->on()->setBrightness(50);
            echo "✓ Turned on at 50% brightness\n";
            
            sleep(2);
            
            // Set to red color (XY coordinates for red)
            $firstLight->setState(['xy' => [0.675, 0.322]]);
            echo "✓ Changed to red\n";
            
            sleep(2);
            
            // Turn off
            $firstLight->off();
            echo "✓ Turned off\n";
        }
    }
    
    // List groups
    echo "\n=== GROUPS ===\n";
    $groups = $client->groups()->getAll();
    
    if (empty($groups)) {
        echo "No groups found.\n";
    } else {
        foreach ($groups as $group) {
            echo sprintf(
                "Group %d: %s (%s) - %d lights\n",
                $group->getId(),
                $group->getName(),
                $group->getType(),
                count($group->getLights())
            );
        }
    }
    
    // List scenes (first 5)
    echo "\n=== SCENES ===\n";
    $scenes = $client->scenes()->getAll();
    
    if (empty($scenes)) {
        echo "No scenes found.\n";
    } else {
        $count = 0;
        foreach ($scenes as $scene) {
            if ($count++ >= 5) {
                echo "... and " . (count($scenes) - 5) . " more scenes\n";
                break;
            }
            echo sprintf(
                "Scene %s: %s\n",
                substr($scene->getId(), 0, 8),
                $scene->getName()
            );
        }
    }
    
    echo "\nExample completed successfully!\n";
    
} catch (HueException $e) {
    echo "Hue Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}