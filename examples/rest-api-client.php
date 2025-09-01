<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * Example: Using the Hue REST API Client
 * 
 * This example demonstrates how to interact with the Hue REST API
 * using standard HTTP requests via cURL or Guzzle.
 */

use GuzzleHttp\Client;

// Configuration
$apiBaseUrl = 'http://localhost:8080/api';
$client = new Client(['base_uri' => $apiBaseUrl]);

echo "🌐 Philips Hue REST API Client Example\n";
echo "=====================================\n\n";

try {
    // 1. Health Check
    echo "1. Checking API health...\n";
    $response = $client->get('/health');
    $health = json_decode($response->getBody(), true);
    
    if ($health['status'] === 'healthy') {
        echo "✅ Bridge is healthy (Response time: {$health['response_time_ms']}ms)\n";
    } else {
        echo "❌ Bridge is unhealthy\n";
        exit(1);
    }

    // 2. Get Bridge Info
    echo "\n2. Getting bridge information...\n";
    $response = $client->get('/bridge/info');
    $bridgeInfo = json_decode($response->getBody(), true);
    echo "🌉 Bridge: {$bridgeInfo['name']} ({$bridgeInfo['modelid']})\n";
    echo "📍 IP: {$bridgeInfo['ip']}\n";
    echo "🔧 SW Version: {$bridgeInfo['swversion']}\n";

    // 3. List All Lights
    echo "\n3. Getting all lights...\n";
    $response = $client->get('/lights');
    $lights = json_decode($response->getBody(), true);
    
    echo "💡 Found " . count($lights) . " lights:\n";
    foreach ($lights as $light) {
        $status = $light['state']['on'] ? 'ON' : 'OFF';
        $brightness = $light['state']['on'] ? " ({$light['state']['brightness']}/254)" : '';
        echo "  {$light['id']}: {$light['name']} - {$status}{$brightness}\n";
    }

    // 4. Get All Rooms
    echo "\n4. Getting all rooms...\n";
    $response = $client->get('/rooms');
    $rooms = json_decode($response->getBody(), true);
    
    echo "🏠 Found " . count($rooms) . " rooms:\n";
    foreach ($rooms as $room) {
        $lightCount = count($room['lights']);
        echo "  {$room['id']}: {$room['name']} ({$lightCount} lights)\n";
    }

    // 5. Control a Light
    if (!empty($lights)) {
        $firstLight = $lights[0];
        echo "\n5. Controlling light: {$firstLight['name']}\n";
        
        // Turn on and set to warm orange
        $response = $client->put("/lights/{$firstLight['id']}/state", [
            'json' => [
                'on' => true,
                'brightness' => 200,
                'hue' => 8000,
                'saturation' => 200
            ]
        ]);
        
        if ($response->getStatusCode() === 200) {
            echo "✅ Light updated successfully\n";
        }

        // Wait a moment
        sleep(2);

        // Set to blue using hex color
        $response = $client->patch("/lights/{$firstLight['id']}/state", [
            'json' => [
                'color' => '#0066FF',
                'brightness' => 75
            ]
        ]);
        
        if ($response->getStatusCode() === 200) {
            echo "✅ Light color changed to blue\n";
        }
    }

    // 6. List Scenes
    echo "\n6. Getting all scenes...\n";
    $response = $client->get('/scenes');
    $scenes = json_decode($response->getBody(), true);
    
    echo "🎬 Found " . count($scenes) . " scenes:\n";
    foreach (array_slice($scenes, 0, 5) as $scene) {
        echo "  {$scene['id']}: {$scene['name']} (Type: {$scene['type']})\n";
    }

    // 7. Activate a Scene (if available)
    if (!empty($scenes)) {
        $firstScene = $scenes[0];
        echo "\n7. Activating scene: {$firstScene['name']}\n";
        
        $response = $client->put("/scenes/{$firstScene['id']}/activate", [
            'json' => ['transitiontime' => 10] // 1 second transition
        ]);
        
        if ($response->getStatusCode() === 200) {
            echo "✅ Scene activated successfully\n";
        }
    }

    // 8. Create a Custom Group
    echo "\n8. Creating a custom group...\n";
    if (count($lights) >= 2) {
        $lightIds = array_slice(array_column($lights, 'id'), 0, 2);
        
        $response = $client->post('/groups', [
            'json' => [
                'name' => 'API Test Group',
                'lights' => $lightIds,
                'type' => 'LightGroup'
            ]
        ]);
        
        $result = json_decode($response->getBody(), true);
        if ($response->getStatusCode() === 200 && $result['success']) {
            echo "✅ Custom group created: {$result['data']['name']}\n";
            
            // Control the new group
            sleep(1);
            $groupId = $result['data']['id'];
            $client->put("/groups/{$groupId}/action", [
                'json' => [
                    'on' => true,
                    'brightness' => 50,
                    'color' => '#FF00FF'
                ]
            ]);
            echo "✅ Custom group controlled\n";
        }
    }

    // 9. Get API Statistics
    echo "\n9. Performance stats...\n";
    $cacheHeaders = $response->getHeaders();
    if (isset($cacheHeaders['X-Cache'])) {
        echo "💾 Cache status: {$cacheHeaders['X-Cache'][0]}\n";
    }
    if (isset($cacheHeaders['X-RateLimit-Remaining'])) {
        echo "🔄 Rate limit remaining: {$cacheHeaders['X-RateLimit-Remaining'][0]}\n";
    }

    echo "\n✅ REST API example completed successfully!\n";
    echo "📚 Visit http://localhost:8080/docs for full API documentation\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "💡 Make sure the API server is running: ./bin/hue-server --discover\n";
    exit(1);
}