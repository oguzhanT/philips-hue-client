<?php

require __DIR__ . '/../vendor/autoload.php';

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;
use OguzhanTogay\HueClient\Effects\Alert;

/**
 * 🔒 Smart Security System with Hue Integration
 * 
 * This example creates a comprehensive security system using Hue lights
 * for visual alerts, deterrence, and status indication.
 * 
 * Features:
 * - Motion detection alerts
 * - Break-in simulation and deterrence
 * - Security patrol lighting
 * - Emergency evacuation lighting
 * - Camera integration with light-based recording indicators
 * - Geofencing with arrival/departure lighting
 * - Silent alarm mode
 * - Integration with Ring, Nest, ADT systems
 */

class SmartSecuritySystem
{
    private HueClient $hue;
    private array $zones;
    private array $securityLights;
    private bool $armedStatus = false;
    private string $securityMode = 'disarmed';
    private array $alertLog = [];

    public function __construct(HueClient $hue)
    {
        $this->hue = $hue;
        $this->setupSecurityZones();
        $this->setupSecurityLights();
    }

    private function setupSecurityZones(): void
    {
        $this->zones = [
            'entry' => [
                'name' => 'Entry Points',
                'lights' => $this->hue->groups()->getByName('Hallway'),
                'sensors' => ['door_sensor', 'window_sensor'],
                'alert_color' => '#FF0000',
                'priority' => 'high'
            ],
            'perimeter' => [
                'name' => 'Perimeter',
                'lights' => $this->hue->groups()->getByName('Garden'),
                'sensors' => ['motion_outdoor', 'camera_motion'],
                'alert_color' => '#FF8000',
                'priority' => 'medium'
            ],
            'interior' => [
                'name' => 'Interior',
                'lights' => $this->hue->groups()->getByName('Living Room'),
                'sensors' => ['motion_indoor', 'glass_break'],
                'alert_color' => '#FFFF00',
                'priority' => 'low'
            ],
            'safe_room' => [
                'name' => 'Safe Room',
                'lights' => $this->hue->groups()->getByName('Bedroom'),
                'sensors' => ['panic_button'],
                'alert_color' => '#0000FF',
                'priority' => 'critical'
            ]
        ];

        echo "🛡️  Security zones configured: " . count($this->zones) . " zones\n";
    }

    private function setupSecurityLights(): void
    {
        $this->securityLights = [
            'status_indicator' => $this->hue->lights()->getByName('Status Light'),
            'driveway' => $this->hue->lights()->getByName('Driveway'),
            'front_door' => $this->hue->lights()->getByName('Front Door'),
            'back_door' => $this->hue->lights()->getByName('Back Door'),
        ];

        echo "💡 Security lights mapped: " . count(array_filter($this->securityLights)) . " lights\n";
    }

    public function armSystem(string $mode = 'away'): void
    {
        $this->securityMode = $mode;
        $this->armedStatus = true;
        
        echo "🔒 Security system ARMED in {$mode} mode\n";
        
        match($mode) {
            'away' => $this->armAwayMode(),
            'home' => $this->armHomeMode(),
            'sleep' => $this->armSleepMode(),
            'vacation' => $this->armVacationMode(),
        };
        
        $this->setStatusIndicator('armed', $mode);
        $this->logSecurityEvent('system_armed', "System armed in {$mode} mode");
    }

    public function disarmSystem(): void
    {
        echo "🔓 Security system DISARMED\n";
        
        $this->armedStatus = false;
        $this->securityMode = 'disarmed';
        
        // Turn off all alert lights
        foreach ($this->zones as $zone) {
            if ($zone['lights']) {
                $zone['lights']->setColor('#FFFFFF');
                $zone['lights']->setBrightness(50);
            }
        }
        
        $this->setStatusIndicator('disarmed');
        $this->logSecurityEvent('system_disarmed', 'System disarmed by user');
    }

    private function armAwayMode(): void
    {
        echo "🏠 Configuring AWAY mode...\n";
        
        // Turn off most lights to simulate empty house
        foreach ($this->zones as $zoneName => $zone) {
            if ($zoneName !== 'perimeter' && $zone['lights']) {
                $zone['lights']->off();
            }
        }
        
        // Keep perimeter lights on for deterrence
        if ($this->zones['perimeter']['lights']) {
            $this->zones['perimeter']['lights']->on();
            $this->zones['perimeter']['lights']->setBrightness(80);
            $this->zones['perimeter']['lights']->setColor('#FFFFFF');
        }
        
        // Start security patrol pattern
        $this->startSecurityPatrol();
    }

    private function armHomeMode(): void
    {
        echo "🏡 Configuring HOME mode...\n";
        
        // Only monitor entry points and perimeter
        foreach (['entry', 'perimeter'] as $zoneName) {
            if ($this->zones[$zoneName]['lights']) {
                $this->zones[$zoneName]['lights']->on();
                $this->zones[$zoneName]['lights']->setBrightness(60);
                $this->zones[$zoneName]['lights']->setColor('#E6E6FA'); // Lavender
            }
        }
    }

    private function armSleepMode(): void
    {
        echo "😴 Configuring SLEEP mode...\n";
        
        // Very dim perimeter lighting
        if ($this->zones['perimeter']['lights']) {
            $this->zones['perimeter']['lights']->on();
            $this->zones['perimeter']['lights']->setBrightness(20);
            $this->zones['perimeter']['lights']->setColor('#4169E1'); // Royal Blue
        }
        
        // Path lighting for safe navigation
        if ($this->securityLights['front_door']) {
            $this->securityLights['front_door']->on();
            $this->securityLights['front_door']->setBrightness(10);
        }
    }

    private function armVacationMode(): void
    {
        echo "✈️  Configuring VACATION mode...\n";
        
        // Simulate occupancy with random lighting patterns
        $this->startOccupancySimulation();
        
        // Enhanced perimeter security
        if ($this->zones['perimeter']['lights']) {
            $this->zones['perimeter']['lights']->on();
            $this->zones['perimeter']['lights']->setBrightness(100);
            $this->zones['perimeter']['lights']->setColor('#FFFFFF');
        }
    }

    public function motionDetected(string $zone, array $sensorData = []): void
    {
        if (!$this->armedStatus) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $confidence = $sensorData['confidence'] ?? 85;
        
        echo "🚨 MOTION DETECTED in {$zone} zone (Confidence: {$confidence}%)\n";
        
        $this->triggerZoneAlert($zone, 'motion_detected', $sensorData);
        $this->logSecurityEvent('motion_detected', "Motion in {$zone} zone", $sensorData);
        
        // Take photos if cameras are available
        $this->triggerCameraRecording($zone);
        
        // Send notifications (integrate with your notification service)
        $this->sendSecurityNotification('Motion Detected', "Movement detected in {$zone} at {$timestamp}");
    }

    public function breakInAttempt(string $zone, array $details = []): void
    {
        echo "🚨🚨 BREAK-IN ATTEMPT DETECTED in {$zone}! 🚨🚨\n";
        
        // Maximum alert mode
        $this->triggerEmergencyAlert($zone);
        
        // Simulate intruder deterrence
        $this->activateIntruderDeterrence();
        
        // Emergency lighting for evacuation
        $this->activateEvacuationLighting();
        
        $this->logSecurityEvent('break_in_attempt', "Break-in attempt in {$zone}", $details);
        $this->sendEmergencyNotification('BREAK-IN ATTEMPT', "Security breach in {$zone} zone!");
    }

    private function triggerZoneAlert(string $zoneName, string $alertType, array $data = []): void
    {
        $zone = $this->zones[$zoneName] ?? null;
        if (!$zone || !$zone['lights']) {
            return;
        }

        // Alert intensity based on zone priority and security mode
        $intensity = match($zone['priority']) {
            'critical' => 100,
            'high' => 80,
            'medium' => 60,
            'low' => 40
        };

        // Different alert patterns based on type
        match($alertType) {
            'motion_detected' => $this->pulseAlert($zone['lights'], $zone['alert_color'], $intensity, 3),
            'door_opened' => $this->flashAlert($zone['lights'], $zone['alert_color'], $intensity, 5),
            'window_broken' => $this->strobeAlert($zone['lights'], $zone['alert_color'], $intensity, 10),
            'panic_button' => $this->emergencyStrobe($zone['lights'], '#FF0000', 100, 30),
        };
    }

    private function activateIntruderDeterrence(): void
    {
        echo "💀 Activating intruder deterrence mode!\n";
        
        // Extremely bright white lights to blind and disorient
        foreach ($this->zones as $zone) {
            if ($zone['lights']) {
                $zone['lights']->setColor('#FFFFFF');
                $zone['lights']->setBrightness(100);
            }
        }
        
        sleep(2);
        
        // Rapid strobing red lights throughout house
        for ($i = 0; $i < 20; $i++) {
            foreach ($this->zones as $zone) {
                if ($zone['lights']) {
                    $zone['lights']->setColor($i % 2 === 0 ? '#FF0000' : '#FFFFFF');
                    $zone['lights']->setBrightness(100);
                }
            }
            usleep(250000); // 250ms strobe
        }
        
        echo "🚨 Deterrence sequence complete\n";
    }

    private function activateEvacuationLighting(): void
    {
        echo "🚪 Activating evacuation lighting...\n";
        
        // Path lighting to exits - bright white
        $evacuationPaths = ['Hallway', 'Stairway', 'Kitchen'];
        
        foreach ($evacuationPaths as $pathName) {
            $path = $this->hue->groups()->getByName($pathName);
            if ($path) {
                $path->on();
                $path->setColor('#FFFFFF');
                $path->setBrightness(100);
                echo "  ✅ {$pathName} evacuation path lit\n";
            }
        }
        
        // Flash exit signs
        if ($this->securityLights['front_door']) {
            $this->flashAlert($this->securityLights['front_door'], '#00FF00', 100, 10);
        }
    }

    private function startSecurityPatrol(): void
    {
        echo "👮 Starting security patrol lighting...\n";
        
        // Create a roaming light pattern to simulate presence
        $patrolRooms = array_filter($this->zones, fn($zone) => $zone['lights']);
        
        // This would run in a background process
        for ($i = 0; $i < 5; $i++) { // Demo: 5 patrol cycles
            foreach ($patrolRooms as $zoneName => $zone) {
                $zone['lights']->on();
                $zone['lights']->setBrightness(40);
                $zone['lights']->setColor('#F0F8FF'); // Alice Blue
                
                echo "  🔦 Patrolling {$zoneName}...\n";
                sleep(3); // Stay in zone for 3 seconds
                
                $zone['lights']->off();
                sleep(1); // Dark period between zones
            }
        }
    }

    private function startOccupancySimulation(): void
    {
        echo "🏠 Starting occupancy simulation for vacation mode...\n";
        
        // Random lighting patterns to simulate people at home
        $rooms = array_filter($this->zones, fn($zone) => $zone['lights']);
        
        for ($day = 1; $day <= 7; $day++) { // 7-day simulation
            echo "📅 Day {$day} occupancy simulation\n";
            
            // Morning routine (6-9 AM)
            $this->simulateTimeOfDay('morning', $rooms);
            
            // Work day (9 AM - 6 PM) - mostly off
            $this->simulateTimeOfDay('work', $rooms);
            
            // Evening (6-11 PM) - active
            $this->simulateTimeOfDay('evening', $rooms);
            
            // Night (11 PM - 6 AM) - sleep mode
            $this->simulateTimeOfDay('night', $rooms);
            
            if ($day < 7) sleep(2); // Speed up for demo
        }
    }

    private function simulateTimeOfDay(string $timeOfDay, array $rooms): void
    {
        $patterns = [
            'morning' => [
                'brightness' => 80,
                'color' => '#FFE4B5', // Warm
                'rooms' => ['interior', 'entry'], // Kitchen, bathroom
                'duration' => 3
            ],
            'work' => [
                'brightness' => 0,
                'color' => '#FFFFFF',
                'rooms' => [], // Mostly dark
                'duration' => 1
            ],
            'evening' => [
                'brightness' => 60,
                'color' => '#F0E68C', // Khaki
                'rooms' => ['interior', 'entry'], // Living areas
                'duration' => 5
            ],
            'night' => [
                'brightness' => 20,
                'color' => '#191970', // Midnight Blue
                'rooms' => ['entry'], // Security only
                'duration' => 1
            ]
        ];

        $pattern = $patterns[$timeOfDay];
        echo "  🕐 {$timeOfDay} simulation ({$pattern['duration']}s)\n";

        foreach ($rooms as $zoneName => $zone) {
            if (in_array($zoneName, $pattern['rooms']) && $zone['lights']) {
                $zone['lights']->on();
                $zone['lights']->setBrightness($pattern['brightness']);
                $zone['lights']->setColor($pattern['color']);
            } else {
                $zone['lights']->off();
            }
        }

        sleep($pattern['duration']);
    }

    public function doorBellPressed(): void
    {
        echo "🔔 Doorbell pressed - Visitor alert\n";
        
        // Gentle notification - not an alarm
        if ($this->securityLights['front_door']) {
            $this->pulseAlert($this->securityLights['front_door'], '#00FF00', 70, 3);
        }
        
        // Record visitor
        $this->triggerCameraRecording('entry');
        $this->logSecurityEvent('doorbell', 'Visitor at front door');
    }

    public function panicButton(): void
    {
        echo "🆘 PANIC BUTTON ACTIVATED! 🆘\n";
        
        // Emergency alert to all lights
        foreach ($this->zones as $zone) {
            if ($zone['lights']) {
                $this->emergencyStrobe($zone['lights'], '#FF0000', 100, 60);
            }
        }
        
        // Send emergency notification
        $this->sendEmergencyNotification('PANIC BUTTON', 'Emergency assistance requested!');
        $this->logSecurityEvent('panic_button', 'Panic button activated');
    }

    public function geofenceEntry(string $userId, array $location): void
    {
        echo "📍 User {$userId} approaching home\n";
        
        // Welcome lighting sequence
        $this->createWelcomeSequence();
        
        // If system is armed, provide disarm reminder
        if ($this->armedStatus) {
            $this->setStatusIndicator('arrival_reminder');
            echo "⏰ Reminder: System is still armed\n";
        }
        
        $this->logSecurityEvent('geofence_entry', "User {$userId} approaching", $location);
    }

    public function geofenceExit(string $userId, array $location): void
    {
        echo "🚗 User {$userId} left home area\n";
        
        // Auto-arm if configured
        if (!$this->armedStatus) {
            echo "🔒 Auto-arming system in AWAY mode...\n";
            $this->armSystem('away');
        }
        
        // Departure lighting - gradually dim everything
        $this->createDepartureSequence();
        
        $this->logSecurityEvent('geofence_exit', "User {$userId} departed", $location);
    }

    private function createWelcomeSequence(): void
    {
        echo "🏠 Creating welcome lighting sequence...\n";
        
        // Progressive lighting from driveway to interior
        $sequence = [
            $this->securityLights['driveway'],
            $this->securityLights['front_door'],
            $this->zones['entry']['lights'],
            $this->zones['interior']['lights']
        ];
        
        foreach ($sequence as $index => $lights) {
            if ($lights) {
                $lights->on();
                $lights->setBrightness(70);
                $lights->setColor('#FFD700'); // Gold welcome
                echo "  ✨ Step " . ($index + 1) . " illuminated\n";
                sleep(2);
            }
        }
    }

    private function createDepartureSequence(): void
    {
        echo "👋 Creating departure lighting sequence...\n";
        
        // Reverse sequence - interior to driveway
        $sequence = [
            $this->zones['interior']['lights'],
            $this->zones['entry']['lights'],
            $this->securityLights['front_door'],
            $this->securityLights['driveway']
        ];
        
        foreach ($sequence as $index => $lights) {
            if ($lights) {
                $lights->setBrightness(20);
                $lights->setColor('#87CEEB'); // Sky Blue farewell
                echo "  🌙 Step " . ($index + 1) . " dimmed\n";
                sleep(2);
                
                if ($index > 0) { // Keep driveway on for safety
                    $lights->off();
                }
            }
        }
    }

    public function cameraMotionDetected(string $cameraId, array $motionData): void
    {
        $confidence = $motionData['confidence'] ?? 0;
        $isPersonDetected = $motionData['person_detected'] ?? false;
        
        echo "📹 Camera {$cameraId} detected motion (Confidence: {$confidence}%)\n";
        
        if ($confidence > 80 && $isPersonDetected && $this->armedStatus) {
            echo "👤 Person detected by camera - High priority alert!\n";
            $this->triggerZoneAlert('perimeter', 'motion_detected', $motionData);
            
            // Turn on recording indicator lights
            $this->activateRecordingIndicators();
        }
        
        $this->logSecurityEvent('camera_motion', "Camera {$cameraId} motion", $motionData);
    }

    private function activateRecordingIndicators(): void
    {
        echo "🔴 Activating camera recording indicators...\n";
        
        // Red pulsing lights to indicate recording
        foreach ($this->securityLights as $name => $light) {
            if ($light && strpos($name, 'door') !== false) {
                $light->setColor('#FF0000');
                $light->setBrightness(100);
                
                // Pulse effect
                for ($i = 0; $i < 5; $i++) {
                    $light->setBrightness(100);
                    usleep(300000);
                    $light->setBrightness(20);
                    usleep(300000);
                }
            }
        }
    }

    public function fireAlarm(): void
    {
        echo "🔥🚨 FIRE ALARM DETECTED! 🚨🔥\n";
        
        // Override all security settings for life safety
        $this->disarmSystem();
        
        // Bright white evacuation lighting
        foreach ($this->zones as $zone) {
            if ($zone['lights']) {
                $zone['lights']->on();
                $zone['lights']->setColor('#FFFFFF');
                $zone['lights']->setBrightness(100);
            }
        }
        
        // Flash exit routes
        $this->activateEvacuationLighting();
        
        $this->logSecurityEvent('fire_alarm', 'Fire alarm triggered - evacuation mode active');
        $this->sendEmergencyNotification('FIRE ALARM', 'Fire detected - evacuate immediately!');
    }

    private function pulseAlert($lights, string $color, int $brightness, int $pulses): void
    {
        for ($i = 0; $i < $pulses; $i++) {
            $lights->setColor($color);
            $lights->setBrightness($brightness);
            usleep(500000); // 500ms on
            
            $lights->setBrightness(10);
            usleep(500000); // 500ms dim
        }
    }

    private function flashAlert($lights, string $color, int $brightness, int $flashes): void
    {
        for ($i = 0; $i < $flashes; $i++) {
            $lights->setColor($color);
            $lights->setBrightness($brightness);
            usleep(200000); // 200ms on
            
            $lights->off();
            usleep(200000); // 200ms off
        }
    }

    private function strobeAlert($lights, string $color, int $brightness, int $duration): void
    {
        $endTime = time() + $duration;
        
        while (time() < $endTime) {
            $lights->setColor($color);
            $lights->setBrightness($brightness);
            usleep(100000); // 100ms on
            
            $lights->off();
            usleep(100000); // 100ms off
        }
    }

    private function emergencyStrobe($lights, string $color, int $brightness, int $duration): void
    {
        $endTime = time() + $duration;
        
        while (time() < $endTime) {
            $lights->setColor($color);
            $lights->setBrightness($brightness);
            usleep(50000); // 50ms on - very rapid
            
            $lights->setColor('#FFFFFF');
            $lights->setBrightness($brightness);
            usleep(50000); // 50ms white
        }
    }

    private function setStatusIndicator(string $status, string $mode = ''): void
    {
        if (!$this->securityLights['status_indicator']) {
            return;
        }

        $light = $this->securityLights['status_indicator'];
        
        match($status) {
            'armed' => $light->setColor('#FF0000')->setBrightness(50), // Red
            'disarmed' => $light->setColor('#00FF00')->setBrightness(30), // Green
            'alert' => $light->setColor('#FF8000')->setBrightness(100), // Orange
            'arrival_reminder' => $light->setColor('#FFFF00')->setBrightness(80), // Yellow
        };
        
        echo "💡 Status indicator: {$status} {$mode}\n";
    }

    private function triggerCameraRecording(string $zone): void
    {
        echo "📹 Triggering camera recording for {$zone} zone\n";
        // In real implementation, this would call your camera system API
        // Example: POST to security camera system, save to cloud storage
    }

    private function sendSecurityNotification(string $title, string $message): void
    {
        echo "📱 Notification: {$title} - {$message}\n";
        // Integrate with your notification service (push notifications, SMS, email)
    }

    private function sendEmergencyNotification(string $title, string $message): void
    {
        echo "🚨📱 EMERGENCY: {$title} - {$message}\n";
        // Immediate emergency notifications to all contacts
    }

    private function logSecurityEvent(string $event, string $description, array $data = []): void
    {
        $logEntry = [
            'timestamp' => date('c'),
            'event' => $event,
            'description' => $description,
            'mode' => $this->securityMode,
            'armed' => $this->armedStatus,
            'data' => $data
        ];
        
        $this->alertLog[] = $logEntry;
        echo "📝 Logged: {$event} - {$description}\n";
    }

    public function getSecurityStatus(): array
    {
        return [
            'armed' => $this->armedStatus,
            'mode' => $this->securityMode,
            'zones' => count($this->zones),
            'recent_events' => array_slice($this->alertLog, -10),
            'bridge_connected' => $this->hue->isConnected(),
        ];
    }

    public function testAllSystems(): void
    {
        echo "🧪 Testing all security systems...\n\n";
        
        // Test each zone
        foreach ($this->zones as $zoneName => $zone) {
            echo "Testing {$zoneName} zone...\n";
            $this->triggerZoneAlert($zoneName, 'motion_detected');
            sleep(2);
        }
        
        echo "\n✅ All systems tested successfully!\n";
    }
}

// Usage Example
try {
    $discovery = new BridgeDiscovery();
    $bridges = $discovery->discover();
    
    if (empty($bridges)) {
        die("❌ No Hue bridges found\n");
    }
    
    $hue = new HueClient($bridges[0]->getIp(), $_ENV['HUE_USERNAME'] ?? null);
    
    if (!$hue->isConnected()) {
        die("❌ Cannot connect to bridge\n");
    }
    
    $security = new SmartSecuritySystem($hue);
    
    echo "🔒 Smart Security System Demo\n";
    echo "============================\n\n";
    
    // Demo sequence
    $security->armSystem('away');
    sleep(2);
    
    $security->motionDetected('perimeter', [
        'confidence' => 92,
        'person_detected' => true,
        'time' => date('H:i:s')
    ]);
    sleep(3);
    
    $security->cameraMotionDetected('front_camera', [
        'confidence' => 95,
        'person_detected' => true,
        'object_size' => 'large'
    ]);
    sleep(2);
    
    $security->doorBellPressed();
    sleep(2);
    
    $security->geofenceEntry('user_123', [
        'lat' => 40.7128,
        'lng' => -74.0060,
        'distance' => 50
    ]);
    sleep(3);
    
    $security->disarmSystem();
    
    echo "\n📊 Security Status:\n";
    print_r($security->getSecurityStatus());
    
} catch (Exception $e) {
    echo "❌ Security system error: " . $e->getMessage() . "\n";
}

/*
Real-world Integrations:

// Ring Doorbell Integration
$ringWebhook = function($data) use ($security) {
    if ($data['kind'] === 'motion') {
        $security->motionDetected('entry', $data);
    } elseif ($data['kind'] === 'ding') {
        $security->doorBellPressed();
    }
};

// ADT Security System Integration
$adtApi = new ADT_API($credentials);
$adtApi->onAlarm(function($alarm) use ($security) {
    match($alarm['type']) {
        'intrusion' => $security->breakInAttempt($alarm['zone']),
        'fire' => $security->fireAlarm(),
        'panic' => $security->panicButton(),
    };
});

// Home Assistant Integration
$mqttClient->subscribe('homeassistant/+/motion/+', function($topic, $message) use ($security) {
    $data = json_decode($message, true);
    if ($data['state'] === 'ON') {
        $zone = basename($topic);
        $security->motionDetected($zone, $data);
    }
});

// SmartThings Integration
$smartThings->onDeviceEvent('motion', function($device, $event) use ($security) {
    $security->motionDetected($device['room'], [
        'device_id' => $device['id'],
        'confidence' => 100,
        'timestamp' => $event['timestamp']
    ]);
});
*/