<?php

require __DIR__ . '/../vendor/autoload.php';

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;

/**
 * 🌤️ Weather-Based Ambient Lighting System
 * 
 * This example creates dynamic lighting that responds to real weather conditions,
 * forecasts, and seasonal changes to bring the outdoors inside.
 * 
 * Features:
 * - Real-time weather condition lighting
 * - Temperature-responsive color temperature
 * - Storm and precipitation effects
 * - Sunrise/sunset synchronization
 * - Seasonal mood adjustments
 * - Weather alerts and warnings
 * - Air quality visualization
 * - Forecast-based pre-lighting
 */

class WeatherBasedLighting
{
    private HueClient $hue;
    private array $rooms;
    private array $weatherColors;
    private array $temperatureColors;
    private string $location;
    private bool $autoMode = true;

    public function __construct(HueClient $hue, string $location = 'New York, NY')
    {
        $this->hue = $hue;
        $this->location = $location;
        $this->setupRooms();
        $this->setupColorSchemes();
    }

    public function startWeatherSync(): void
    {
        echo "🌤️ Starting Weather-Based Lighting for {$this->location}\n";
        echo "📡 Fetching current weather data...\n";
        
        $this->autoMode = true;
        
        // Initial weather sync
        $this->syncWithCurrentWeather();
        
        // Start monitoring loop
        $this->weatherMonitoringLoop();
    }

    private function setupRooms(): void
    {
        $this->rooms = [
            'living_room' => $this->hue->groups()->getByName('Living Room'),
            'bedroom' => $this->hue->groups()->getByName('Bedroom'),
            'kitchen' => $this->hue->groups()->getByName('Kitchen'),
            'office' => $this->hue->groups()->getByName('Office'),
            'hallway' => $this->hue->groups()->getByName('Hallway'),
        ];

        // Filter out non-existent rooms
        $this->rooms = array_filter($this->rooms);
        
        echo "🏠 Weather-synced rooms: " . implode(', ', array_keys($this->rooms)) . "\n";
    }

    private function setupColorSchemes(): void
    {
        $this->weatherColors = [
            'clear' => '#FFE135',        // Bright yellow
            'partly_cloudy' => '#87CEEB', // Light blue
            'cloudy' => '#D3D3D3',       // Light gray
            'overcast' => '#808080',     // Gray
            'rain' => '#4682B4',         // Steel blue
            'heavy_rain' => '#1E3A8A',   // Dark blue
            'snow' => '#F0F8FF',         // Alice blue
            'storm' => '#2F1B69',        // Dark purple
            'fog' => '#F5F5DC',          // Beige
            'mist' => '#E0E0E0',         // Light gray
        ];

        $this->temperatureColors = [
            'freezing' => '#87CEEB',     // Light blue (< 32°F)
            'cold' => '#ADD8E6',         // Light blue (32-50°F)
            'cool' => '#E0FFFF',         // Light cyan (50-65°F)
            'comfortable' => '#F0F8FF',  // Alice blue (65-75°F)
            'warm' => '#FFFFE0',         // Light yellow (75-85°F)
            'hot' => '#FFB347',          // Peach (85-95°F)
            'scorching' => '#FF6347',    // Tomato (> 95°F)
        ];
    }

    public function syncWithCurrentWeather(): void
    {
        $weather = $this->getCurrentWeather();
        
        echo "🌡️  Current: {$weather['condition']} | {$weather['temp']}°F | Humidity: {$weather['humidity']}%\n";
        echo "💨 Wind: {$weather['wind_speed']} mph | UV: {$weather['uv_index']}\n";
        
        $this->applyWeatherLighting($weather);
        $this->applyTemperatureLighting($weather['temp']);
        
        // Special effects for severe weather
        if ($weather['alerts']) {
            $this->handleWeatherAlerts($weather['alerts']);
        }
    }

    private function getCurrentWeather(): array
    {
        // Simulate weather API response (replace with real API like OpenWeatherMap)
        $conditions = ['clear', 'partly_cloudy', 'cloudy', 'rain', 'snow', 'storm'];
        $condition = $conditions[array_rand($conditions)];
        
        return [
            'condition' => $condition,
            'temp' => rand(20, 95),
            'humidity' => rand(30, 90),
            'wind_speed' => rand(0, 25),
            'uv_index' => rand(1, 11),
            'pressure' => rand(29, 31),
            'visibility' => rand(1, 10),
            'sunrise' => '06:30',
            'sunset' => '19:45',
            'alerts' => rand(0, 10) > 8 ? ['Severe Thunderstorm Warning'] : [],
            'air_quality' => rand(1, 6), // 1=Good, 6=Hazardous
        ];
    }

    private function applyWeatherLighting(array $weather): void
    {
        $condition = $weather['condition'];
        $baseColor = $this->weatherColors[$condition] ?? $this->weatherColors['clear'];
        
        echo "🎨 Applying {$condition} lighting theme\n";
        
        foreach ($this->rooms as $roomName => $room) {
            $room->setColor($baseColor);
            
            // Brightness based on weather visibility and time
            $brightness = $this->calculateWeatherBrightness($weather, $roomName);
            $room->setBrightness($brightness);
            
            echo "  ✅ {$roomName}: {$baseColor} @ {$brightness}%\n";
        }
        
        // Apply special weather effects
        match($condition) {
            'storm' => $this->stormEffect(),
            'rain' => $this->rainEffect($weather['intensity'] ?? 'light'),
            'snow' => $this->snowEffect(),
            'fog' => $this->fogEffect(),
            default => null
        };
    }

    private function calculateWeatherBrightness(array $weather, string $room): int
    {
        $baseBrightness = match($weather['condition']) {
            'clear' => 80,
            'partly_cloudy' => 65,
            'cloudy' => 50,
            'overcast' => 40,
            'rain', 'heavy_rain' => 35,
            'snow' => 60,
            'storm' => 25,
            'fog', 'mist' => 45,
            default => 50
        };
        
        // Adjust for room function
        $roomMultiplier = match($room) {
            'office' => 1.2,      // Brighter for work
            'kitchen' => 1.1,     // Slightly brighter for tasks
            'bedroom' => 0.8,     // Dimmer for relaxation
            'living_room' => 1.0, // Standard
            default => 1.0
        };
        
        return min(100, max(10, (int)($baseBrightness * $roomMultiplier)));
    }

    private function applyTemperatureLighting(int $temp): void
    {
        $tempCategory = match(true) {
            $temp < 32 => 'freezing',
            $temp < 50 => 'cold',
            $temp < 65 => 'cool',
            $temp < 75 => 'comfortable',
            $temp < 85 => 'warm',
            $temp < 95 => 'hot',
            default => 'scorching'
        };
        
        $tempColor = $this->temperatureColors[$tempCategory];
        
        echo "🌡️  Temperature lighting: {$tempCategory} ({$temp}°F) -> {$tempColor}\n";
        
        // Apply temperature tint to specific rooms
        if (isset($this->rooms['living_room'])) {
            $this->rooms['living_room']->setColor($tempColor);
        }
    }

    private function stormEffect(): void
    {
        echo "⛈️  STORM EFFECT ACTIVE\n";
        
        // Lightning simulation
        for ($i = 0; $i < rand(3, 8); $i++) {
            // Lightning flash
            foreach ($this->rooms as $room) {
                $room->setColor('#FFFFFF');
                $room->setBrightness(100);
            }
            usleep(100000); // 100ms flash
            
            // Back to storm colors
            foreach ($this->rooms as $room) {
                $room->setColor('#2F1B69');
                $room->setBrightness(20);
            }
            
            // Random delay between lightning strikes
            sleep(rand(2, 8));
        }
    }

    private function rainEffect(string $intensity = 'light'): void
    {
        echo "🌧️  Rain effect: {$intensity}\n";
        
        $pulseSpeed = match($intensity) {
            'light' => 2000000,    // 2 seconds
            'moderate' => 1000000, // 1 second
            'heavy' => 500000,     // 0.5 seconds
        };
        
        $brightness = match($intensity) {
            'light' => 40,
            'moderate' => 30,
            'heavy' => 20,
        };
        
        // Gentle pulsing to simulate rain
        for ($i = 0; $i < 10; $i++) {
            foreach ($this->rooms as $room) {
                $room->setBrightness($brightness + 10);
            }
            usleep($pulseSpeed / 2);
            
            foreach ($this->rooms as $room) {
                $room->setBrightness($brightness);
            }
            usleep($pulseSpeed / 2);
        }
    }

    private function snowEffect(): void
    {
        echo "❄️  Snow effect active\n";
        
        // Soft, slow fade effect
        $snowColors = ['#F0F8FF', '#E6E6FA', '#F8F8FF', '#FFFAFA'];
        
        foreach ($snowColors as $color) {
            foreach ($this->rooms as $room) {
                $room->setColor($color);
                $room->setBrightness(60);
            }
            sleep(3); // Slow, peaceful transitions
        }
    }

    private function fogEffect(): void
    {
        echo "🌫️  Fog effect active\n";
        
        // Gradual dimming to simulate reduced visibility
        for ($brightness = 50; $brightness >= 20; $brightness -= 5) {
            foreach ($this->rooms as $room) {
                $room->setBrightness($brightness);
            }
            sleep(1);
        }
    }

    public function sunriseSunsetSync(): void
    {
        echo "🌅 Synchronizing with natural light cycle\n";
        
        $weather = $this->getCurrentWeather();
        $currentTime = date('H:i');
        $sunrise = $weather['sunrise'];
        $sunset = $weather['sunset'];
        
        echo "☀️  Sunrise: {$sunrise} | 🌅 Sunset: {$sunset} | Current: {$currentTime}\n";
        
        $timeCategory = $this->determineTimeOfDay($currentTime, $sunrise, $sunset);
        
        match($timeCategory) {
            'pre_sunrise' => $this->preSunriseEffect(),
            'sunrise' => $this->sunriseEffect(),
            'morning' => $this->morningEffect(),
            'afternoon' => $this->afternoonEffect(),
            'golden_hour' => $this->goldenHourEffect(),
            'sunset' => $this->sunsetEffect(),
            'evening' => $this->eveningEffect(),
            'night' => $this->nightEffect(),
        };
    }

    private function determineTimeOfDay(string $current, string $sunrise, string $sunset): string
    {
        $currentMinutes = $this->timeToMinutes($current);
        $sunriseMinutes = $this->timeToMinutes($sunrise);
        $sunsetMinutes = $this->timeToMinutes($sunset);
        
        return match(true) {
            $currentMinutes < $sunriseMinutes - 30 => 'pre_sunrise',
            $currentMinutes < $sunriseMinutes + 30 => 'sunrise',
            $currentMinutes < $sunriseMinutes + 180 => 'morning',
            $currentMinutes < $sunsetMinutes - 60 => 'afternoon',
            $currentMinutes < $sunsetMinutes => 'golden_hour',
            $currentMinutes < $sunsetMinutes + 30 => 'sunset',
            $currentMinutes < $sunsetMinutes + 120 => 'evening',
            default => 'night'
        };
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);
        return ($hours * 60) + $minutes;
    }

    private function sunriseEffect(): void
    {
        echo "🌅 Sunrise lighting effect\n";
        
        foreach ($this->rooms as $room) {
            $room->sunrise(1800); // 30-minute gentle sunrise
        }
    }

    private function sunsetEffect(): void
    {
        echo "🌇 Sunset lighting effect\n";
        
        foreach ($this->rooms as $room) {
            $room->sunset(1800); // 30-minute gentle sunset
        }
    }

    private function goldenHourEffect(): void
    {
        echo "✨ Golden hour lighting\n";
        
        foreach ($this->rooms as $room) {
            $room->setColor('#FFD700');
            $room->setBrightness(70);
        }
    }

    public function weatherAlert(string $alertType, array $details = []): void
    {
        echo "🚨 WEATHER ALERT: {$alertType}\n";
        
        $alertColor = match($alertType) {
            'tornado_warning' => '#8B008B',      // Dark magenta
            'severe_thunderstorm' => '#FF4500',  // Orange red
            'flood_warning' => '#0000FF',        // Blue
            'winter_storm' => '#FFFFFF',         // White
            'heat_warning' => '#FF0000',         // Red
            'wind_advisory' => '#FFFF00',        // Yellow
            'fog_advisory' => '#D3D3D3',         // Light gray
            default => '#FF8000'                 // Orange
        };
        
        $this->emergencyAlertSequence($alertColor, $alertType);
    }

    private function emergencyAlertSequence(string $color, string $alertType): void
    {
        echo "🚨 Emergency lighting sequence for {$alertType}\n";
        
        // Urgent flashing pattern
        for ($i = 0; $i < 10; $i++) {
            foreach ($this->rooms as $room) {
                $room->setColor($color);
                $room->setBrightness(100);
            }
            usleep(500000); // 500ms on
            
            foreach ($this->rooms as $room) {
                $room->setBrightness(0);
            }
            usleep(500000); // 500ms off
        }
        
        // Keep alert color at lower intensity
        foreach ($this->rooms as $room) {
            $room->setColor($color);
            $room->setBrightness(30);
        }
    }

    public function airQualityVisualization(array $aqiData): void
    {
        $aqi = $aqiData['aqi'] ?? rand(1, 300);
        $category = $this->getAQICategory($aqi);
        
        echo "💨 Air Quality: {$aqi} AQI ({$category})\n";
        
        $aqiColor = match($category) {
            'good' => '#00FF00',           // Green
            'moderate' => '#FFFF00',       // Yellow
            'unhealthy_sensitive' => '#FF8000', // Orange
            'unhealthy' => '#FF0000',      // Red
            'very_unhealthy' => '#8B008B', // Purple
            'hazardous' => '#800000',      // Maroon
        };
        
        // Visualize air quality in specific room (like entryway)
        if (isset($this->rooms['hallway'])) {
            $this->rooms['hallway']->setColor($aqiColor);
            $this->rooms['hallway']->setBrightness(60);
            echo "  🏠 Air quality visualization in hallway\n";
        }
    }

    private function getAQICategory(int $aqi): string
    {
        return match(true) {
            $aqi <= 50 => 'good',
            $aqi <= 100 => 'moderate',
            $aqi <= 150 => 'unhealthy_sensitive',
            $aqi <= 200 => 'unhealthy',
            $aqi <= 300 => 'very_unhealthy',
            default => 'hazardous'
        };
    }

    public function seasonalAdjustment(): void
    {
        $month = (int)date('n');
        $season = match(true) {
            in_array($month, [12, 1, 2]) => 'winter',
            in_array($month, [3, 4, 5]) => 'spring',
            in_array($month, [6, 7, 8]) => 'summer',
            default => 'autumn'
        };
        
        echo "🍂 Seasonal adjustment: {$season}\n";
        
        $seasonalColors = match($season) {
            'winter' => ['#E0E0E0', '#87CEEB', '#F0F8FF'],
            'spring' => ['#90EE90', '#FFB6C1', '#98FB98'],
            'summer' => ['#FFD700', '#FF6347', '#32CD32'],
            'autumn' => ['#FF4500', '#DAA520', '#CD853F'],
        };
        
        foreach ($this->rooms as $roomName => $room) {
            $color = $seasonalColors[array_rand($seasonalColors)];
            $room->setColor($color);
            echo "  🎨 {$roomName} themed for {$season}\n";
        }
    }

    public function forecastPreLighting(array $forecast): void
    {
        echo "📅 Setting up lighting for upcoming weather\n";
        
        foreach ($forecast as $day => $weather) {
            echo "  📆 {$day}: {$weather['condition']} | {$weather['high']}°/{$weather['low']}°\n";
            
            // Pre-adjust for tomorrow's weather
            if ($day === 'tomorrow' && $weather['condition'] === 'storm') {
                echo "  ⚡ Pre-staging storm lighting for tomorrow\n";
                $this->preStageStormLighting();
            }
        }
    }

    private function preStageStormLighting(): void
    {
        // Gradually darken lights in preparation
        foreach ($this->rooms as $room) {
            $room->setColor('#4682B4');
            $room->setBrightness(40);
        }
    }

    public function weatherMonitoringLoop(): void
    {
        echo "🔄 Starting weather monitoring loop (Press Ctrl+C to stop)\n\n";
        
        $lastUpdate = 0;
        $updateInterval = 300; // 5 minutes
        
        while ($this->autoMode) {
            $currentTime = time();
            
            if ($currentTime - $lastUpdate >= $updateInterval) {
                echo "🔄 Updating weather lighting...\n";
                $this->syncWithCurrentWeather();
                
                // Check for sunrise/sunset every hour
                if ($currentTime % 3600 === 0) {
                    $this->sunriseSunsetSync();
                }
                
                // Seasonal check once per day
                if (date('H:i') === '00:00') {
                    $this->seasonalAdjustment();
                }
                
                $lastUpdate = $currentTime;
            }
            
            sleep(60); // Check every minute
        }
    }

    public function vacationMode(int $days): void
    {
        echo "✈️  Vacation Mode: {$days} days\n";
        
        // Simulate presence with weather-aware lighting
        for ($day = 1; $day <= $days; $day++) {
            echo "📅 Day {$day}: Simulating presence\n";
            
            // Morning routine
            $this->sunriseEffect();
            
            // Daytime with weather sync
            $this->syncWithCurrentWeather();
            
            // Evening routine
            $this->sunsetEffect();
            
            // Night mode
            $this->nightEffect();
        }
    }

    public function extremeWeatherMode(string $weatherType): void
    {
        echo "🌪️  EXTREME WEATHER MODE: {$weatherType}\n";
        
        match($weatherType) {
            'hurricane' => $this->hurricaneEffect(),
            'tornado' => $this->tornadoEffect(),
            'blizzard' => $this->blizzardEffect(),
            'heatwave' => $this->heatwaveEffect(),
            'derecho' => $this->derechoEffect(),
            default => $this->handleUnknownWeatherType($weatherType)
        };
    }

    private function hurricaneEffect(): void
    {
        echo "🌀 Hurricane simulation\n";
        
        // Swirling effect with wind colors
        $windColors = ['#1E3A8A', '#2563EB', '#3B82F6'];
        
        for ($i = 0; $i < 20; $i++) {
            foreach ($this->rooms as $room) {
                $room->setColor($windColors[$i % count($windColors)]);
                $room->setBrightness(rand(20, 60));
            }
            usleep(300000);
        }
    }

    private function blizzardEffect(): void
    {
        echo "❄️  Blizzard simulation\n";
        
        // Chaotic white flashing to simulate snow
        for ($i = 0; $i < 30; $i++) {
            foreach ($this->rooms as $room) {
                $room->setColor('#FFFFFF');
                $room->setBrightness(rand(30, 90));
            }
            usleep(rand(100000, 400000));
        }
    }

    public function moonPhaseSync(): void
    {
        $moonPhase = $this->getCurrentMoonPhase();
        echo "🌙 Moon phase: {$moonPhase}\n";
        
        $moonBrightness = match($moonPhase) {
            'new_moon' => 5,
            'waxing_crescent' => 15,
            'first_quarter' => 30,
            'waxing_gibbous' => 45,
            'full_moon' => 60,
            'waning_gibbous' => 45,
            'last_quarter' => 30,
            'waning_crescent' => 15,
        };
        
        // Apply moonlight simulation for night hours
        if (date('H') >= 21 || date('H') <= 6) {
            foreach ($this->rooms as $room) {
                $room->setColor('#C0C0C0'); // Silver moonlight
                $room->setBrightness($moonBrightness);
            }
        }
    }

    private function getCurrentMoonPhase(): string
    {
        $phases = ['new_moon', 'waxing_crescent', 'first_quarter', 'waxing_gibbous', 
                  'full_moon', 'waning_gibbous', 'last_quarter', 'waning_crescent'];
        return $phases[array_rand($phases)];
    }

    public function handleWeatherAlerts(array $alerts): void
    {
        foreach ($alerts as $alert) {
            echo "⚠️  Weather Alert: {$alert}\n";
            
            $alertType = strtolower(str_replace(' ', '_', $alert));
            $this->weatherAlert($alertType);
        }
    }

    public function outdoorEventLighting(string $eventType): void
    {
        echo "🏞️  Outdoor event: {$eventType}\n";
        
        match($eventType) {
            'bbq' => $this->bbqLighting(),
            'pool_party' => $this->poolPartyLighting(),
            'garden_work' => $this->gardenWorkLighting(),
            'stargazing' => $this->stargazingLighting(),
            default => $this->handleUnknownOutdoorEvent($eventType)
        };
    }

    private function bbqLighting(): void
    {
        foreach ($this->rooms as $room) {
            $room->setColor('#FF4500'); // Warm orange like fire
            $room->setBrightness(75);
        }
    }

    private function stargazingLighting(): void
    {
        foreach ($this->rooms as $room) {
            $room->setColor('#000080'); // Dark blue
            $room->setBrightness(5);    // Very dim to preserve night vision
        }
    }

    public function stopWeatherSync(): void
    {
        echo "\n🛑 Stopping weather sync...\n";
        $this->autoMode = false;
        
        // Return to comfortable neutral lighting
        foreach ($this->rooms as $roomName => $room) {
            $room->setColor('#F0F8FF'); // Alice blue
            $room->setBrightness(50);
            echo "  💡 {$roomName} returned to neutral\n";
            usleep(500000);
        }
        
        echo "✅ Weather sync stopped. Manual control restored.\n";
    }

    // Helper methods for time-based effects
    private function preSunriseEffect(): void
    {
        foreach ($this->rooms as $room) {
            $room->setColor('#1E1E3F');
            $room->setBrightness(10);
        }
    }

    private function morningEffect(): void
    {
        foreach ($this->rooms as $room) {
            $room->setColor('#FFE4B5');
            $room->setBrightness(80);
        }
    }

    private function afternoonEffect(): void
    {
        foreach ($this->rooms as $room) {
            $room->setColor('#FFFFFF');
            $room->setBrightness(90);
        }
    }

    private function eveningEffect(): void
    {
        foreach ($this->rooms as $room) {
            $room->setColor('#FFE4B5');
            $room->setBrightness(60);
        }
    }

    private function nightEffect(): void
    {
        foreach ($this->rooms as $room) {
            $room->setColor('#1E1E3F');
            $room->setBrightness(15);
        }
    }

    private function heatwaveEffect(): void
    {
        echo "🔥 Heatwave simulation\n";
        
        $heatColors = ['#FF0000', '#FF4500', '#FF6347'];
        
        foreach ($this->rooms as $room) {
            $room->setColor($heatColors[array_rand($heatColors)]);
            $room->setBrightness(90);
        }
    }

    private function tornadoEffect(): void
    {
        echo "🌪️  Tornado simulation\n";
        
        // Rapid swirling effect
        $rooms = array_values($this->rooms);
        for ($i = 0; $i < 20; $i++) {
            $activeRoom = $rooms[$i % count($rooms)];
            $activeRoom->setColor('#8B008B');
            $activeRoom->setBrightness(100);
            usleep(200000);
            $activeRoom->setBrightness(20);
        }
    }

    private function derechoEffect(): void
    {
        echo "💨 Derecho (land hurricane) simulation\n";
        
        // Sustained high winds effect
        for ($i = 0; $i < 15; $i++) {
            foreach ($this->rooms as $room) {
                $room->setColor('#4682B4');
                $room->setBrightness(rand(40, 80));
            }
            usleep(400000);
        }
    }

    private function poolPartyLighting(): void
    {
        foreach ($this->rooms as $room) {
            $room->setColor('#00FFFF');
            $room->setBrightness(85);
        }
    }

    private function gardenWorkLighting(): void
    {
        foreach ($this->rooms as $room) {
            $room->setColor('#32CD32');
            $room->setBrightness(90);
        }
    }

    private function handleUnknownOutdoorEvent(string $eventType): void
    {
        echo "  ❓ Unknown outdoor event: {$eventType}\n";
    }

    private function handleUnknownWeatherType(string $weatherType): void
    {
        echo "  ❓ Unknown weather type: {$weatherType}\n";
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
        die("❌ Cannot connect to bridge. Check your username.\n");
    }
    
    $weather = new WeatherBasedLighting($hue, 'San Francisco, CA');
    
    echo "🌤️ Weather-Based Lighting System\n";
    echo "================================\n\n";
    
    // Demo current weather sync
    $weather->syncWithCurrentWeather();
    sleep(3);
    
    // Demo seasonal adjustment
    $weather->seasonalAdjustment();
    sleep(2);
    
    // Demo sunrise/sunset sync
    $weather->sunriseSunsetSync();
    sleep(3);
    
    // Demo weather alert
    $weather->weatherAlert('severe_thunderstorm', [
        'wind_speed' => '75 mph',
        'hail_size' => '1 inch'
    ]);
    sleep(5);
    
    // Demo air quality
    $weather->airQualityVisualization(['aqi' => 165]);
    sleep(3);
    
    // Demo extreme weather
    $weather->extremeWeatherMode('hurricane');
    sleep(5);
    
    // Demo moon phase sync
    $weather->moonPhaseSync();
    sleep(3);
    
    // Demo forecast pre-lighting
    $weather->forecastPreLighting([
        'today' => ['condition' => 'clear', 'high' => 78, 'low' => 55],
        'tomorrow' => ['condition' => 'storm', 'high' => 65, 'low' => 48],
        'wednesday' => ['condition' => 'rain', 'high' => 60, 'low' => 45],
    ]);
    sleep(3);
    
    echo "\n🌤️ Starting 30-second weather monitoring demo...\n";
    
    // Run monitoring for demonstration
    $endTime = time() + 30;
    while (time() < $endTime) {
        $weather->syncWithCurrentWeather();
        sleep(10);
    }
    
    $weather->stopWeatherSync();
    
} catch (Exception $e) {
    echo "❌ Weather system error: " . $e->getMessage() . "\n";
}

/*
Real-world Weather Integration Examples:

// OpenWeatherMap API Integration
function fetchRealWeather($apiKey, $location) {
    $url = "https://api.openweathermap.org/data/2.5/weather?q={$location}&appid={$apiKey}&units=imperial";
    $response = file_get_contents($url);
    return json_decode($response, true);
}

// Weather.gov API (US only, no API key required)
function fetchNWSWeather($lat, $lon) {
    $pointUrl = "https://api.weather.gov/points/{$lat},{$lon}";
    $pointData = json_decode(file_get_contents($pointUrl), true);
    
    $forecastUrl = $pointData['properties']['forecast'];
    $alertsUrl = "https://api.weather.gov/alerts/active?point={$lat},{$lon}";
    
    return [
        'forecast' => json_decode(file_get_contents($forecastUrl), true),
        'alerts' => json_decode(file_get_contents($alertsUrl), true)
    ];
}

// Home Assistant Integration
$ha = new HomeAssistant('http://homeassistant.local:8123', $token);

$ha->subscribeToEvent('state_changed', function($event) use ($weather) {
    if ($event['entity_id'] === 'weather.home') {
        $weather->syncWithCurrentWeather();
    }
});

// Webhooks for weather services
// POST /webhook/weather
$weatherData = json_decode(file_get_contents('php://input'), true);
$weather->syncWithCurrentWeather($weatherData);

// IFTTT Integration
// IF Weather changes THEN POST to your webhook endpoint

// Smart home sensor integration
function processSensorData($sensorData) {
    if ($sensorData['type'] === 'outdoor_temp') {
        $weather->applyTemperatureLighting($sensorData['value']);
    }
    
    if ($sensorData['type'] === 'rain_sensor' && $sensorData['value'] > 0) {
        $weather->rainEffect('moderate');
    }
}

// Cron job for regular updates
// */5 * * * * php /path/to/weather-sync.php

// WebSocket real-time weather updates
$loop = React\EventLoop\Factory::create();
$connector = new React\Socket\Connector($loop);

$connector->connect('ws://weatherws.example.com')->then(function ($connection) use ($weather) {
    $connection->on('message', function ($msg) use ($weather) {
        $data = json_decode($msg->getPayload(), true);
        $weather->syncWithCurrentWeather($data);
    });
});

$loop->run();
*/