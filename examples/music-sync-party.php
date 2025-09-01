<?php

require __DIR__ . '/../vendor/autoload.php';

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;
use OguzhanTogay\HueClient\Effects\ColorLoop;

/**
 * 🎵 Music-Synced Party Lighting System
 * 
 * This example creates an immersive party experience by syncing Hue lights
 * with music beats, volume levels, and genre detection.
 * 
 * Features:
 * - Real-time beat detection
 * - Volume-responsive brightness
 * - Genre-based color schemes
 * - Multi-room synchronization
 * - Bass drop effects
 * - Spotify/Apple Music integration
 */

class MusicSyncParty
{
    private HueClient $hue;
    private array $rooms;
    private array $beatColors;
    private array $genreSchemes;
    private bool $isActive = false;
    private float $lastBeat = 0;
    private int $bpm = 120;

    public function __construct(HueClient $hue)
    {
        $this->hue = $hue;
        $this->setupRooms();
        $this->setupColorSchemes();
    }

    public function startParty(array $options = []): void
    {
        echo "🎉 Starting Music-Synced Party Mode!\n";
        
        $this->isActive = true;
        $this->bpm = $options['bpm'] ?? 120;
        $genre = $options['genre'] ?? 'electronic';
        $intensity = $options['intensity'] ?? 'high';
        
        echo "🎵 Genre: {$genre} | BPM: {$this->bpm} | Intensity: {$intensity}\n";
        
        // Initialize all rooms for party
        $this->initializePartyLighting($genre, $intensity);
        
        // Start the music sync loop
        $this->musicSyncLoop($genre, $intensity);
    }

    private function setupRooms(): void
    {
        $this->rooms = [
            'living_room' => $this->hue->groups()->getByName('Living Room'),
            'kitchen' => $this->hue->groups()->getByName('Kitchen'),
            'bedroom' => $this->hue->groups()->getByName('Bedroom'),
            'hallway' => $this->hue->groups()->getByName('Hallway'),
        ];

        // Filter out non-existent rooms
        $this->rooms = array_filter($this->rooms);
        
        echo "🏠 Party venues: " . implode(', ', array_keys($this->rooms)) . "\n";
    }

    private function setupColorSchemes(): void
    {
        $this->beatColors = [
            '#FF0080', '#00FF80', '#8000FF', '#FF8000',
            '#0080FF', '#80FF00', '#FF0040', '#40FF00'
        ];

        $this->genreSchemes = [
            'electronic' => ['#00FFFF', '#FF00FF', '#FFFF00', '#FF0080'],
            'rock' => ['#FF0000', '#FF8000', '#FFFF00', '#FFFFFF'],
            'jazz' => ['#FFD700', '#FF6347', '#4169E1', '#8A2BE2'],
            'classical' => ['#F0F8FF', '#E6E6FA', '#DDA0DD', '#B0C4DE'],
            'hip_hop' => ['#FF1493', '#00CED1', '#FFD700', '#FF4500'],
            'ambient' => ['#2E8B57', '#4682B4', '#9370DB', '#20B2AA'],
        ];
    }

    private function initializePartyLighting(string $genre, string $intensity): void
    {
        echo "🌈 Initializing party lighting...\n";
        
        $baseIntensity = match($intensity) {
            'low' => 30,
            'medium' => 60,
            'high' => 90,
            'extreme' => 100
        };

        foreach ($this->rooms as $roomName => $room) {
            $room->on();
            $room->setBrightness($baseIntensity);
            
            // Set initial genre-based color
            $colors = $this->genreSchemes[$genre] ?? $this->genreSchemes['electronic'];
            $room->setColor($colors[array_rand($colors)]);
            
            echo "  ✅ {$roomName} initialized\n";
            usleep(200000); // Smooth startup
        }
    }

    private function musicSyncLoop(string $genre, string $intensity): void
    {
        echo "🎵 Starting music sync (Press Ctrl+C to stop)...\n\n";
        
        $beatInterval = 60 / $this->bpm; // Seconds between beats
        $colors = $this->genreSchemes[$genre];
        $loopCount = 0;
        
        while ($this->isActive) {
            $currentTime = microtime(true);
            
            // Simulate beat detection (in real app, this would come from audio analysis)
            if ($currentTime - $this->lastBeat >= $beatInterval) {
                $this->onBeat($colors, $intensity, $loopCount);
                $this->lastBeat = $currentTime;
                $loopCount++;
            }
            
            // Simulate volume changes (bass drops, build-ups)
            if ($loopCount % 16 === 0) { // Every 16 beats (~8 seconds at 120 BPM)
                $this->onBassDropBuild($colors, $loopCount);
            }
            
            // Breathe effect between major sections
            if ($loopCount % 64 === 0) { // Every 64 beats (~32 seconds)
                $this->onSectionChange($genre, $colors);
            }
            
            usleep(50000); // 50ms update rate for smooth effects
        }
    }

    private function onBeat(array $colors, string $intensity, int $beatCount): void
    {
        $beatColor = $colors[$beatCount % count($colors)];
        
        // Alternate between rooms for ping-pong effect
        $roomKeys = array_keys($this->rooms);
        $activeRoom = $roomKeys[$beatCount % count($roomKeys)];
        
        // Beat flash effect
        foreach ($this->rooms as $roomName => $room) {
            if ($roomName === $activeRoom) {
                // Main beat room - bright flash
                $room->setColor($beatColor);
                $room->setBrightness(100);
                echo "💥 BEAT in {$roomName} - {$beatColor}\n";
            } else {
                // Other rooms - subtle pulse
                $room->setBrightness(match($intensity) {
                    'low' => 20,
                    'medium' => 40,
                    'high' => 60,
                    'extreme' => 80
                });
            }
        }
        
        // Quick fade after beat
        usleep(100000); // 100ms flash duration
        
        foreach ($this->rooms as $room) {
            $room->setBrightness(match($intensity) {
                'low' => 15,
                'medium' => 35,
                'high' => 55,
                'extreme' => 75
            });
        }
    }

    private function onBassDropBuild(array $colors, int $loopCount): void
    {
        $isBuildUp = ($loopCount % 32) < 16;
        
        if ($isBuildUp) {
            echo "⬆️  BUILD UP DETECTED!\n";
            
            // Gradual brightness increase
            for ($i = 30; $i <= 100; $i += 10) {
                foreach ($this->rooms as $room) {
                    $room->setBrightness($i);
                    $room->setColor($colors[array_rand($colors)]);
                }
                usleep(200000); // 200ms between steps
            }
        } else {
            echo "💥 BASS DROP! 💥\n";
            
            // Explosive effect - all rooms flash white then return to colors
            foreach ($this->rooms as $room) {
                $room->setColor('#FFFFFF');
                $room->setBrightness(100);
            }
            
            usleep(300000); // Hold the white flash
            
            // Return to party colors with strobe effect
            for ($i = 0; $i < 5; $i++) {
                foreach ($this->rooms as $roomName => $room) {
                    $room->setColor($this->beatColors[array_rand($this->beatColors)]);
                    $room->setBrightness($i % 2 === 0 ? 100 : 20);
                }
                usleep(150000); // 150ms strobe
            }
        }
    }

    private function onSectionChange(string $genre, array $colors): void
    {
        echo "🔄 SECTION CHANGE - Genre transition\n";
        
        // Smooth color transition across all rooms
        $transitionColor = $colors[array_rand($colors)];
        
        foreach ($this->rooms as $roomName => $room) {
            $room->setColor($transitionColor);
            $room->setBrightness(70);
            echo "  🎨 {$roomName} -> {$transitionColor}\n";
            usleep(500000); // 500ms delay between rooms for wave effect
        }
    }

    public function bassBoost(): void
    {
        echo "🔊 BASS BOOST ACTIVATED!\n";
        
        // Ultra-bright flash with bass colors
        $bassColors = ['#FF0000', '#FF8000', '#FFFF00'];
        
        foreach ($this->rooms as $room) {
            $room->setColor($bassColors[array_rand($bassColors)]);
            $room->setBrightness(100);
        }
        
        usleep(500000); // Hold for 500ms
        
        // Return to party mode
        foreach ($this->rooms as $room) {
            $room->setBrightness(80);
        }
    }

    public function createMusicVisualization(array $audioData): void
    {
        // Simulate frequency analysis data
        $bass = $audioData['bass'] ?? rand(20, 100);
        $mid = $audioData['mid'] ?? rand(20, 100);
        $treble = $audioData['treble'] ?? rand(20, 100);
        $volume = $audioData['volume'] ?? rand(40, 100);
        
        echo "🎼 Audio: Bass:{$bass} Mid:{$mid} Treble:{$treble} Vol:{$volume}\n";
        
        foreach ($this->rooms as $roomName => $room) {
            // Map frequency ranges to colors
            $color = match(true) {
                $bass > 70 => '#FF0000',      // Red for heavy bass
                $mid > 70 => '#00FF00',       // Green for prominent mids
                $treble > 70 => '#0000FF',    // Blue for bright trebles
                default => '#FFFFFF'          // White for balanced
            };
            
            // Volume controls brightness
            $brightness = min(100, max(10, $volume));
            
            $room->setColor($color);
            $room->setBrightness($brightness);
        }
    }

    public function spotifyIntegration(): void
    {
        echo "🎧 Spotify Integration Active\n";
        
        // Simulate Spotify API data
        $trackInfo = [
            'name' => 'Levels',
            'artist' => 'Avicii',
            'genre' => 'electronic',
            'energy' => 0.9,
            'tempo' => 126,
            'valence' => 0.8,
            'danceability' => 0.95
        ];
        
        echo "🎵 Now Playing: {$trackInfo['name']} by {$trackInfo['artist']}\n";
        
        // Adjust lighting based on track characteristics
        $this->bpm = $trackInfo['tempo'];
        $colors = $this->genreSchemes[$trackInfo['genre']] ?? $this->beatColors;
        
        $intensity = match(true) {
            $trackInfo['energy'] > 0.8 => 'extreme',
            $trackInfo['energy'] > 0.6 => 'high',
            $trackInfo['energy'] > 0.4 => 'medium',
            default => 'low'
        };
        
        // Happy songs = brighter, sad songs = dimmer
        $valenceBrightness = (int)($trackInfo['valence'] * 100);
        
        foreach ($this->rooms as $room) {
            $room->setColor($colors[array_rand($colors)]);
            $room->setBrightness($valenceBrightness);
        }
        
        echo "⚡ Energy: {$trackInfo['energy']} | Intensity: {$intensity} | Mood: {$valenceBrightness}%\n";
    }

    public function stopParty(): void
    {
        echo "\n🛑 Stopping party mode...\n";
        $this->isActive = false;
        
        // Gentle fade to normal lighting
        foreach ($this->rooms as $roomName => $room) {
            $room->setColor('#FFE4B5'); // Warm white
            $room->setBrightness(40);
            echo "  💤 {$roomName} returning to normal\n";
            usleep(1000000); // 1 second delay between rooms
        }
        
        echo "✅ Party ended. Thanks for the amazing night! 🌙\n";
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
    
    $party = new MusicSyncParty($hue);
    
    echo "🎵 Music Sync Party Controller\n";
    echo "=============================\n\n";
    
    // Start party with electronic music
    $party->startParty([
        'genre' => 'electronic',
        'bpm' => 128,
        'intensity' => 'extreme'
    ]);
    
    // Simulate track changes and effects
    sleep(5);
    $party->bassBoost();
    
    sleep(3);
    $party->spotifyIntegration();
    
    sleep(5);
    $party->createMusicVisualization([
        'bass' => 85,
        'mid' => 60,
        'treble' => 75,
        'volume' => 95
    ]);
    
    // Let the party run for a bit
    echo "\n🎉 Party is in full swing! Let it run for 30 seconds...\n";
    sleep(30);
    
    $party->stopParty();
    
} catch (Exception $e) {
    echo "❌ Party crashed: " . $e->getMessage() . "\n";
}

/*
Real-world Integration Examples:

// Spotify Web API Integration
function getSpotifyCurrentTrack($accessToken) {
    $response = file_get_contents("https://api.spotify.com/v1/me/player/currently-playing", false, stream_context_create([
        'http' => [
            'header' => "Authorization: Bearer {$accessToken}"
        ]
    ]));
    return json_decode($response, true);
}

// Web Audio API (for browser-based music sync)
<script>
navigator.mediaDevices.getUserMedia({ audio: true })
    .then(stream => {
        const audioContext = new AudioContext();
        const analyser = audioContext.createAnalyser();
        const microphone = audioContext.createMediaStreamSource(stream);
        
        microphone.connect(analyser);
        analyser.fftSize = 256;
        
        const dataArray = new Uint8Array(analyser.frequencyBinCount);
        
        function analyzeBeat() {
            analyser.getByteFrequencyData(dataArray);
            
            const bass = dataArray.slice(0, 10).reduce((a, b) => a + b) / 10;
            const mid = dataArray.slice(10, 50).reduce((a, b) => a + b) / 40;
            const treble = dataArray.slice(50, 100).reduce((a, b) => a + b) / 50;
            
            // Send to PHP backend
            fetch('/api/hue/music-sync', {
                method: 'POST',
                body: JSON.stringify({ bass, mid, treble, volume: (bass + mid + treble) / 3 })
            });
            
            requestAnimationFrame(analyzeBeat);
        }
        
        analyzeBeat();
    });
</script>

// MQTT Integration for real-time music data
$mqtt = new PhpMqtt\Client\MqttClient($server, $port, $clientId);
$mqtt->connect();

$mqtt->subscribe('music/beat', function ($topic, $message) use ($party) {
    $beatData = json_decode($message, true);
    $party->createMusicVisualization($beatData);
});

$mqtt->loop(true);
*/