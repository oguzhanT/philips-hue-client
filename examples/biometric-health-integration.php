<?php

require __DIR__ . '/../vendor/autoload.php';

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;

/**
 * 💊 Biometric & Health Integration System
 * 
 * This example creates health-aware lighting that responds to biometric data,
 * fitness activities, sleep patterns, and wellness metrics to enhance well-being.
 * 
 * Features:
 * - Heart rate responsive lighting
 * - Sleep cycle optimization
 * - Workout intensity visualization
 * - Stress level indicators
 * - Circadian rhythm support
 * - Meditation and mindfulness modes
 * - Health goal progress tracking
 * - Emergency health alerts
 */

class BiometricHealthIntegration
{
    private HueClient $hue;
    private array $zones;
    private array $healthThresholds;
    private array $currentMetrics;
    private bool $monitoringActive = false;
    private string $currentMode = 'normal';

    public function __construct(HueClient $hue)
    {
        $this->hue = $hue;
        $this->setupZones();
        $this->setupHealthThresholds();
        $this->initializeMetrics();
    }

    public function startHealthMonitoring(array $options = []): void
    {
        echo "💊 Starting Biometric Health Monitoring\n";
        
        $this->monitoringActive = true;
        $syncWithWearables = $options['wearables'] ?? true;
        $circadianMode = $options['circadian'] ?? true;
        
        echo "⌚ Wearable sync: " . ($syncWithWearables ? 'ON' : 'OFF') . "\n";
        echo "🌅 Circadian mode: " . ($circadianMode ? 'ON' : 'OFF') . "\n";
        
        if ($circadianMode) {
            $this->initializeCircadianLighting();
        }
        
        $this->healthMonitoringLoop();
    }

    private function setupZones(): void
    {
        $this->zones = [
            'bedroom' => $this->hue->groups()->getByName('Bedroom'),
            'workout' => $this->hue->groups()->getByName('Gym'),
            'meditation' => $this->hue->lights()->getByName('Meditation Corner'),
            'office' => $this->hue->groups()->getByName('Office'),
            'bathroom' => $this->hue->lights()->getByName('Bathroom Mirror'),
            'kitchen' => $this->hue->groups()->getByName('Kitchen'),
        ];

        $this->zones = array_filter($this->zones);
        
        echo "🏠 Health monitoring zones: " . implode(', ', array_keys($this->zones)) . "\n";
    }

    private function setupHealthThresholds(): void
    {
        $this->healthThresholds = [
            'heart_rate' => [
                'resting' => 60,
                'low_cardio' => 120,
                'moderate_cardio' => 150,
                'high_cardio' => 180,
                'max_safe' => 200,
            ],
            'stress_level' => [
                'relaxed' => 20,
                'normal' => 50,
                'elevated' => 70,
                'high_stress' => 90,
            ],
            'sleep_quality' => [
                'poor' => 30,
                'fair' => 50,
                'good' => 70,
                'excellent' => 90,
            ],
            'steps' => [
                'sedentary' => 2000,
                'lightly_active' => 5000,
                'moderately_active' => 8000,
                'very_active' => 12000,
                'extremely_active' => 15000,
            ]
        ];
    }

    private function initializeMetrics(): void
    {
        $this->currentMetrics = [
            'heart_rate' => 70,
            'stress_level' => 30,
            'sleep_quality' => 85,
            'steps_today' => 0,
            'calories_burned' => 0,
            'active_minutes' => 0,
            'water_intake' => 0,
            'blood_oxygen' => 98,
            'sleep_stage' => 'awake',
        ];
    }

    public function updateHeartRate(int $heartRate, string $context = 'resting'): void
    {
        $this->currentMetrics['heart_rate'] = $heartRate;
        
        echo "❤️  Heart Rate: {$heartRate} BPM ({$context})\n";
        
        $hrZone = $this->getHeartRateZone($heartRate);
        $this->applyHeartRateLighting($hrZone, $heartRate);
        
        // Emergency detection
        if ($heartRate > $this->healthThresholds['heart_rate']['max_safe']) {
            $this->emergencyHeartRateAlert($heartRate);
        }
    }

    private function getHeartRateZone(int $hr): string
    {
        return match(true) {
            $hr < $this->healthThresholds['heart_rate']['resting'] => 'below_resting',
            $hr < $this->healthThresholds['heart_rate']['low_cardio'] => 'resting',
            $hr < $this->healthThresholds['heart_rate']['moderate_cardio'] => 'low_cardio',
            $hr < $this->healthThresholds['heart_rate']['high_cardio'] => 'moderate_cardio',
            $hr < $this->healthThresholds['heart_rate']['max_safe'] => 'high_cardio',
            default => 'dangerous'
        };
    }

    private function applyHeartRateLighting(string $zone, int $hr): void
    {
        $hrColor = match($zone) {
            'below_resting' => '#87CEEB',    // Light blue - very calm
            'resting' => '#00FF00',          // Green - healthy
            'low_cardio' => '#FFFF00',       // Yellow - light activity
            'moderate_cardio' => '#FF8000',  // Orange - moderate activity
            'high_cardio' => '#FF4500',      // Red-orange - intense
            'dangerous' => '#FF0000',        // Red - danger
        };
        
        $intensity = min(100, max(20, ($hr - 40) * 1.5)); // Scale brightness with HR
        
        if (isset($this->zones['workout'])) {
            $this->zones['workout']->setColor($hrColor);
            $this->zones['workout']->setBrightness($intensity);
        }
        
        // Pulse effect for active zones
        if ($zone !== 'resting' && $zone !== 'below_resting') {
            $this->heartRatePulseEffect($hrColor, $hr);
        }
    }

    private function heartRatePulseEffect(string $color, int $hr): void
    {
        $pulseRate = 60000000 / $hr; // Microseconds between pulses
        
        for ($i = 0; $i < 3; $i++) {
            if (isset($this->zones['workout'])) {
                $this->zones['workout']->setBrightness(100);
                usleep($pulseRate / 4);
                $this->zones['workout']->setBrightness(60);
                usleep($pulseRate * 3 / 4);
            }
        }
    }

    private function emergencyHeartRateAlert(int $hr): void
    {
        echo "🚨 EMERGENCY: Dangerous heart rate detected ({$hr} BPM)\n";
        
        // Flash all zones red for emergency
        for ($i = 0; $i < 10; $i++) {
            foreach ($this->zones as $zone) {
                $zone->setColor('#FF0000');
                $zone->setBrightness(100);
            }
            usleep(200000);
            
            foreach ($this->zones as $zone) {
                $zone->setBrightness(0);
            }
            usleep(200000);
        }
    }

    public function updateStressLevel(int $stressLevel): void
    {
        $this->currentMetrics['stress_level'] = $stressLevel;
        
        echo "😰 Stress Level: {$stressLevel}%\n";
        
        $stressCategory = match(true) {
            $stressLevel <= $this->healthThresholds['stress_level']['relaxed'] => 'relaxed',
            $stressLevel <= $this->healthThresholds['stress_level']['normal'] => 'normal',
            $stressLevel <= $this->healthThresholds['stress_level']['elevated'] => 'elevated',
            default => 'high_stress'
        };
        
        $this->applyStressLighting($stressCategory, $stressLevel);
        
        // Recommend relaxation if stress is high
        if ($stressLevel >= $this->healthThresholds['stress_level']['high_stress']) {
            $this->recommendRelaxation();
        }
    }

    private function applyStressLighting(string $category, int $level): void
    {
        $stressColor = match($category) {
            'relaxed' => '#00FF00',     // Green - calm
            'normal' => '#FFFF00',      // Yellow - normal
            'elevated' => '#FF8000',    // Orange - elevated
            'high_stress' => '#FF0000', // Red - high stress
        };
        
        if (isset($this->zones['office'])) {
            $this->zones['office']->setColor($stressColor);
            $this->zones['office']->setBrightness(max(30, 100 - $level));
        }
    }

    private function recommendRelaxation(): void
    {
        echo "🧘 High stress detected. Activating relaxation mode...\n";
        $this->meditationMode(300); // 5-minute session
    }

    public function sleepCycleOptimization(string $stage, array $sleepData = []): void
    {
        $this->currentMetrics['sleep_stage'] = $stage;
        
        echo "😴 Sleep Stage: {$stage}\n";
        
        match($stage) {
            'bedtime_prep' => $this->bedtimePreparation(),
            'light_sleep' => $this->lightSleepLighting(),
            'deep_sleep' => $this->deepSleepLighting(),
            'rem_sleep' => $this->remSleepLighting(),
            'wake_prep' => $this->wakePreparation(),
            'wake_up' => $this->naturalWakeUp(),
        };
    }

    private function bedtimePreparation(): void
    {
        echo "🌙 Preparing for bedtime - reducing blue light\n";
        
        if (isset($this->zones['bedroom'])) {
            $this->zones['bedroom']->setColor('#FF6347'); // Warm red
            $this->zones['bedroom']->setBrightness(20);
        }
        
        // Gradually dim over 30 minutes
        $this->gradualDimming(30);
    }

    private function gradualDimming(int $minutes): void
    {
        $steps = $minutes * 2; // Update every 30 seconds
        
        for ($step = 0; $step < $steps; $step++) {
            $brightness = max(5, 20 - ($step * 15 / $steps));
            
            if (isset($this->zones['bedroom'])) {
                $this->zones['bedroom']->setBrightness((int)$brightness);
            }
            
            sleep(30); // 30-second intervals
        }
    }

    private function naturalWakeUp(): void
    {
        echo "☀️  Natural wake-up sequence starting\n";
        
        if (isset($this->zones['bedroom'])) {
            $this->zones['bedroom']->sunrise(1800); // 30-minute sunrise
        }
        
        echo "  🌅 Sunrise simulation active for 30 minutes\n";
    }

    public function workoutMode(string $workoutType, int $durationMinutes = 30): void
    {
        echo "💪 Workout Mode: {$workoutType} ({$durationMinutes} min)\n";
        
        $this->currentMode = 'workout';
        
        match($workoutType) {
            'cardio' => $this->cardioLighting(),
            'strength' => $this->strengthLighting(),
            'yoga' => $this->yogaLighting(),
            'hiit' => $this->hiitLighting(),
            'cool_down' => $this->coolDownLighting(),
        };
        
        // Start workout monitoring
        $this->workoutProgressTracking($durationMinutes);
    }

    private function cardioLighting(): void
    {
        echo "🏃 Cardio lighting activated\n";
        
        if (isset($this->zones['workout'])) {
            $this->zones['workout']->setColor('#00FFFF'); // Energizing cyan
            $this->zones['workout']->setBrightness(90);
        }
    }

    private function strengthLighting(): void
    {
        echo "🏋️  Strength training lighting\n";
        
        if (isset($this->zones['workout'])) {
            $this->zones['workout']->setColor('#FF4500'); // Motivating orange
            $this->zones['workout']->setBrightness(85);
        }
    }

    private function yogaLighting(): void
    {
        echo "🧘 Yoga lighting activated\n";
        
        if (isset($this->zones['workout'])) {
            $this->zones['workout']->setColor('#9370DB'); // Calming purple
            $this->zones['workout']->setBrightness(40);
        }
    }

    private function hiitLighting(): void
    {
        echo "⚡ HIIT lighting - high intensity\n";
        
        // Dynamic color cycling for high intensity
        $hiitColors = ['#FF0000', '#FF8000', '#FFFF00', '#00FF00'];
        
        for ($i = 0; $i < 20; $i++) {
            if (isset($this->zones['workout'])) {
                $this->zones['workout']->setColor($hiitColors[$i % count($hiitColors)]);
                $this->zones['workout']->setBrightness(100);
            }
            sleep(2); // Change every 2 seconds
        }
    }

    private function workoutProgressTracking(int $totalMinutes): void
    {
        echo "⏱️  Tracking workout progress ({$totalMinutes} min total)\n";
        
        $interval = max(1, $totalMinutes / 10); // 10 progress updates
        
        for ($elapsed = 0; $elapsed < $totalMinutes; $elapsed += $interval) {
            $progress = ($elapsed / $totalMinutes) * 100;
            echo "  📊 Progress: {$progress}%\n";
            
            // Update lighting intensity based on progress
            $intensity = 60 + ($progress * 0.4); // Increase intensity as workout progresses
            
            if (isset($this->zones['workout'])) {
                $this->zones['workout']->setBrightness((int)$intensity);
            }
            
            sleep($interval * 60); // Convert to seconds
        }
        
        echo "✅ Workout completed!\n";
        $this->workoutComplete();
    }

    private function workoutComplete(): void
    {
        echo "🎉 Workout complete! Activating recovery lighting\n";
        
        // Cool celebration
        $this->celebrationSequence();
        
        // Switch to cool down lighting
        $this->coolDownLighting();
    }

    private function celebrationSequence(): void
    {
        $celebrationColors = ['#FFD700', '#00FF00', '#00FFFF'];
        
        for ($i = 0; $i < 6; $i++) {
            if (isset($this->zones['workout'])) {
                $this->zones['workout']->setColor($celebrationColors[$i % count($celebrationColors)]);
                $this->zones['workout']->setBrightness(100);
            }
            usleep(500000);
        }
    }

    private function coolDownLighting(): void
    {
        echo "❄️  Cool down lighting\n";
        
        if (isset($this->zones['workout'])) {
            $this->zones['workout']->setColor('#87CEEB'); // Light blue
            $this->zones['workout']->setBrightness(50);
        }
    }

    public function meditationMode(int $durationMinutes = 10): void
    {
        echo "🧘 Meditation Mode ({$durationMinutes} min)\n";
        
        $this->currentMode = 'meditation';
        
        // Set calming meditation lighting
        if (isset($this->zones['meditation'])) {
            $this->zones['meditation']->setColor('#9370DB'); // Calming purple
            $this->zones['meditation']->setBrightness(25);
        }
        
        // Optional breathing guidance lighting
        $this->breathingGuidance($durationMinutes);
    }

    private function breathingGuidance(int $minutes): void
    {
        echo "  🫁 Starting breathing guidance\n";
        
        $cycles = $minutes * 6; // 6 breath cycles per minute
        
        for ($cycle = 0; $cycle < $cycles; $cycle++) {
            // Inhale - gradual brighten (4 seconds)
            $this->breatheIn();
            
            // Hold (4 seconds)
            sleep(4);
            
            // Exhale - gradual dim (4 seconds)
            $this->breatheOut();
            
            // Rest (2 seconds)
            sleep(2);
            
            if ($cycle % 6 === 0) {
                echo "  🧘 Minute " . ($cycle / 6 + 1) . " completed\n";
            }
        }
        
        echo "✅ Meditation session complete\n";
    }

    private function breatheIn(): void
    {
        if (isset($this->zones['meditation'])) {
            // Gradual brightness increase over 4 seconds
            for ($i = 25; $i <= 50; $i += 2) {
                $this->zones['meditation']->setBrightness($i);
                usleep(160000); // ~4 seconds total
            }
        }
    }

    private function breatheOut(): void
    {
        if (isset($this->zones['meditation'])) {
            // Gradual brightness decrease over 4 seconds
            for ($i = 50; $i >= 25; $i -= 2) {
                $this->zones['meditation']->setBrightness($i);
                usleep(160000); // ~4 seconds total
            }
        }
    }

    public function sleepTrackingIntegration(array $sleepData): void
    {
        $efficiency = $sleepData['efficiency'] ?? 85;
        $duration = $sleepData['duration_hours'] ?? 7.5;
        $deepSleepPercent = $sleepData['deep_sleep_percent'] ?? 20;
        $remPercent = $sleepData['rem_percent'] ?? 25;
        
        echo "😴 Sleep Report:\n";
        echo "  ⏰ Duration: {$duration}h | Efficiency: {$efficiency}%\n";
        echo "  🌊 Deep Sleep: {$deepSleepPercent}% | 👁️  REM: {$remPercent}%\n";
        
        $this->currentMetrics['sleep_quality'] = $efficiency;
        
        // Morning lighting based on sleep quality
        $this->applySleepQualityLighting($efficiency);
    }

    private function applySleepQualityLighting(int $efficiency): void
    {
        $qualityColor = match(true) {
            $efficiency >= 90 => '#00FF00',   // Green - excellent sleep
            $efficiency >= 75 => '#FFFF00',   // Yellow - good sleep
            $efficiency >= 60 => '#FF8000',   // Orange - fair sleep
            default => '#FF0000'              // Red - poor sleep
        };
        
        if (isset($this->zones['bedroom'])) {
            $this->zones['bedroom']->setColor($qualityColor);
            $this->zones['bedroom']->setBrightness(30);
            
            // Gentle morning wake based on sleep quality
            if ($efficiency < 70) {
                echo "  😴 Poor sleep detected - extra gentle wake-up\n";
                $this->extraGentleWakeUp();
            }
        }
    }

    private function extraGentleWakeUp(): void
    {
        if (isset($this->zones['bedroom'])) {
            // Very slow, gentle sunrise over 45 minutes
            $this->zones['bedroom']->sunrise(2700);
        }
    }

    public function fitnessGoalProgress(array $goals, array $current): void
    {
        echo "🎯 Fitness Goal Progress:\n";
        
        foreach ($goals as $metric => $target) {
            $currentValue = $current[$metric] ?? 0;
            $progress = min(100, ($currentValue / $target) * 100);
            
            echo "  📊 {$metric}: {$currentValue}/{$target} ({$progress}%)\n";
            
            $this->visualizeGoalProgress($metric, $progress);
        }
    }

    private function visualizeGoalProgress(string $metric, float $progress): void
    {
        $progressColor = match(true) {
            $progress >= 100 => '#FFD700', // Gold - goal achieved
            $progress >= 75 => '#00FF00',  // Green - on track
            $progress >= 50 => '#FFFF00',  // Yellow - halfway
            $progress >= 25 => '#FF8000',  // Orange - getting started
            default => '#FF0000'           // Red - needs attention
        };
        
        $targetZone = match($metric) {
            'steps' => 'hallway',
            'calories' => 'kitchen',
            'active_minutes' => 'workout',
            'water_intake' => 'kitchen',
            default => 'office'
        };
        
        if (isset($this->zones[$targetZone])) {
            $this->zones[$targetZone]->setColor($progressColor);
            $this->zones[$targetZone]->setBrightness((int)($progress * 0.8) + 20);
        }
    }

    public function hydrationReminder(int $waterIntake, int $target = 8): void
    {
        $this->currentMetrics['water_intake'] = $waterIntake;
        
        echo "💧 Water intake: {$waterIntake}/{$target} glasses\n";
        
        $progress = ($waterIntake / $target) * 100;
        
        if ($progress < 50 && date('H') > 12) {
            echo "  💦 Hydration reminder activated\n";
            $this->hydrationReminderEffect();
        }
    }

    private function hydrationReminderEffect(): void
    {
        if (isset($this->zones['kitchen'])) {
            // Gentle blue pulsing reminder
            for ($i = 0; $i < 5; $i++) {
                $this->zones['kitchen']->setColor('#87CEEB');
                $this->zones['kitchen']->setBrightness(60);
                sleep(1);
                $this->zones['kitchen']->setBrightness(30);
                sleep(1);
            }
        }
    }

    public function circadianRhythmSupport(): void
    {
        echo "🌅 Circadian rhythm support active\n";
        
        $hour = (int)date('H');
        
        $circadianSetting = match(true) {
            $hour >= 6 && $hour < 9 => 'morning_energy',
            $hour >= 9 && $hour < 12 => 'peak_focus',
            $hour >= 12 && $hour < 15 => 'afternoon_steady',
            $hour >= 15 && $hour < 18 => 'evening_wind_down',
            $hour >= 18 && $hour < 21 => 'dinner_social',
            $hour >= 21 && $hour < 23 => 'evening_relax',
            default => 'night_rest'
        };
        
        $this->applyCircadianLighting($circadianSetting);
    }

    private function applyCircadianLighting(string $setting): void
    {
        echo "  🔄 Circadian setting: {$setting}\n";
        
        $circadianConfig = match($setting) {
            'morning_energy' => ['color' => '#FFFFFF', 'brightness' => 80],
            'peak_focus' => ['color' => '#F0F8FF', 'brightness' => 90],
            'afternoon_steady' => ['color' => '#FFFACD', 'brightness' => 75],
            'evening_wind_down' => ['color' => '#FFE4B5', 'brightness' => 60],
            'dinner_social' => ['color' => '#FFEFD5', 'brightness' => 70],
            'evening_relax' => ['color' => '#FF6347', 'brightness' => 40],
            'night_rest' => ['color' => '#8B0000', 'brightness' => 10],
        };
        
        foreach ($this->zones as $roomName => $room) {
            if ($roomName !== 'workout') { // Don't interfere with workout lighting
                $room->setColor($circadianConfig['color']);
                $room->setBrightness($circadianConfig['brightness']);
            }
        }
    }

    public function healthEmergencyProtocol(string $emergencyType, array $data = []): void
    {
        echo "🚨 HEALTH EMERGENCY: {$emergencyType}\n";
        
        match($emergencyType) {
            'fall_detected' => $this->fallEmergencyLighting(),
            'heart_arrhythmia' => $this->heartEmergencyLighting(),
            'panic_attack' => $this->panicAttackSupport(),
            'seizure_detected' => $this->seizureEmergencyLighting(),
            'medication_reminder' => $this->medicationReminderLighting(),
        };
    }

    private function fallEmergencyLighting(): void
    {
        echo "🚨 Fall detected - activating emergency lighting\n";
        
        // Bright white flashing for emergency responders
        for ($i = 0; $i < 15; $i++) {
            foreach ($this->zones as $zone) {
                $zone->setColor('#FFFFFF');
                $zone->setBrightness(100);
            }
            usleep(500000);
            
            foreach ($this->zones as $zone) {
                $zone->setBrightness(0);
            }
            usleep(500000);
        }
        
        // Keep bright for emergency services
        foreach ($this->zones as $zone) {
            $zone->setColor('#FFFFFF');
            $zone->setBrightness(100);
        }
    }

    private function panicAttackSupport(): void
    {
        echo "💙 Panic attack support - activating calming sequence\n";
        
        // Slow, calming blue breathing pattern
        for ($i = 0; $i < 10; $i++) {
            foreach ($this->zones as $zone) {
                $zone->setColor('#87CEEB');
                $zone->setBrightness(60);
            }
            sleep(4); // Inhale
            
            foreach ($this->zones as $zone) {
                $zone->setBrightness(20);
            }
            sleep(6); // Exhale
        }
    }

    public function medicationScheduleIntegration(array $medications): void
    {
        echo "💊 Medication schedule integration\n";
        
        foreach ($medications as $med) {
            $nextDose = $med['next_dose'];
            $medName = $med['name'];
            
            echo "  💊 {$medName}: Next dose at {$nextDose}\n";
            
            // Set reminder lighting if dose is due soon
            if ($this->isDoseDueSoon($nextDose)) {
                $this->medicationReminderLighting($medName);
            }
        }
    }

    private function isDoseDueSoon(string $doseTime): bool
    {
        $doseTimestamp = strtotime($doseTime);
        $currentTimestamp = time();
        $timeDiff = $doseTimestamp - $currentTimestamp;
        
        return $timeDiff <= 300 && $timeDiff > 0; // Within 5 minutes
    }

    private function medicationReminderLighting(string $medName): void
    {
        echo "  💊 Medication reminder: {$medName}\n";
        
        if (isset($this->zones['bathroom'])) {
            // Gentle purple pulsing
            for ($i = 0; $i < 8; $i++) {
                $this->zones['bathroom']->setColor('#9370DB');
                $this->zones['bathroom']->setBrightness(70);
                sleep(1);
                $this->zones['bathroom']->setBrightness(30);
                sleep(1);
            }
        }
    }

    public function bloodPressureAlert(int $systolic, int $diastolic): void
    {
        echo "🩺 Blood Pressure: {$systolic}/{$diastolic} mmHg\n";
        
        $bpCategory = $this->categorizeBP($systolic, $diastolic);
        
        if (in_array($bpCategory, ['stage1_hypertension', 'stage2_hypertension', 'crisis'])) {
            echo "⚠️  Elevated blood pressure detected\n";
            $this->bloodPressureWarningLighting($bpCategory);
        }
    }

    private function categorizeBP(int $systolic, int $diastolic): string
    {
        return match(true) {
            $systolic < 120 && $diastolic < 80 => 'normal',
            $systolic < 130 && $diastolic < 80 => 'elevated',
            ($systolic >= 130 && $systolic < 140) || ($diastolic >= 80 && $diastolic < 90) => 'stage1_hypertension',
            $systolic >= 140 || $diastolic >= 90 => 'stage2_hypertension',
            $systolic >= 180 || $diastolic >= 120 => 'crisis',
            default => 'unknown'
        };
    }

    private function bloodPressureWarningLighting(string $category): void
    {
        $warningColor = match($category) {
            'stage1_hypertension' => '#FF8000', // Orange
            'stage2_hypertension' => '#FF4500', // Red-orange
            'crisis' => '#FF0000',              // Red
        };
        
        if (isset($this->zones['bathroom'])) {
            $this->zones['bathroom']->setColor($warningColor);
            $this->zones['bathroom']->setBrightness(80);
        }
    }

    public function moodTracking(string $mood, int $energy = 50): void
    {
        echo "😊 Mood: {$mood} | Energy: {$energy}%\n";
        
        $moodColor = match($mood) {
            'happy' => '#FFFF00',      // Yellow
            'excited' => '#FF6347',    // Orange-red
            'calm' => '#87CEEB',       // Light blue
            'focused' => '#FFFFFF',    // White
            'tired' => '#4B0082',      // Indigo
            'stressed' => '#FF0000',   // Red
            'anxious' => '#FF8000',    // Orange
            'sad' => '#0000FF',        // Blue
            default => '#F0F8FF'       // Alice blue
        };
        
        $brightness = max(20, min(90, $energy));
        
        if (isset($this->zones['office'])) {
            $this->zones['office']->setColor($moodColor);
            $this->zones['office']->setBrightness($brightness);
        }
    }

    public function healthMetricsDashboard(): void
    {
        echo "\n📊 Health Metrics Dashboard\n";
        echo "===========================\n";
        echo "❤️  Heart Rate: {$this->currentMetrics['heart_rate']} BPM\n";
        echo "😰 Stress Level: {$this->currentMetrics['stress_level']}%\n";
        echo "😴 Sleep Quality: {$this->currentMetrics['sleep_quality']}%\n";
        echo "👟 Steps Today: {$this->currentMetrics['steps_today']}\n";
        echo "🔥 Calories: {$this->currentMetrics['calories_burned']}\n";
        echo "⏰ Active Minutes: {$this->currentMetrics['active_minutes']}\n";
        echo "💧 Water Intake: {$this->currentMetrics['water_intake']} glasses\n";
        echo "🫁 Blood Oxygen: {$this->currentMetrics['blood_oxygen']}%\n";
        
        // Create visual dashboard in lighting
        $this->createLightingDashboard();
    }

    private function createLightingDashboard(): void
    {
        // Use different rooms to show different metrics
        $rooms = array_keys($this->zones);
        $metrics = ['heart_rate', 'stress_level', 'sleep_quality', 'steps_today'];
        
        foreach ($metrics as $index => $metric) {
            if (isset($rooms[$index]) && isset($this->zones[$rooms[$index]])) {
                $room = $this->zones[$rooms[$index]];
                $value = $this->currentMetrics[$metric];
                
                $color = $this->getMetricColor($metric, $value);
                $brightness = $this->getMetricBrightness($metric, $value);
                
                $room->setColor($color);
                $room->setBrightness($brightness);
                
                echo "  📍 {$rooms[$index]}: {$metric} visualization\n";
            }
        }
    }

    private function getMetricColor(string $metric, $value): string
    {
        return match($metric) {
            'heart_rate' => $value > 100 ? '#FF0000' : '#00FF00',
            'stress_level' => $value > 70 ? '#FF0000' : '#00FF00',
            'sleep_quality' => $value > 70 ? '#00FF00' : '#FF8000',
            'steps_today' => $value > 8000 ? '#00FF00' : '#FFFF00',
            default => '#FFFFFF'
        };
    }

    private function getMetricBrightness(string $metric, $value): int
    {
        return match($metric) {
            'heart_rate' => min(100, max(20, $value - 40)),
            'stress_level' => 100 - $value,
            'sleep_quality' => $value,
            'steps_today' => min(100, ($value / 100) + 20),
            default => 50
        };
    }

    private function healthMonitoringLoop(): void
    {
        echo "🔄 Health monitoring active (Press Ctrl+C to stop)\n\n";
        
        while ($this->monitoringActive) {
            // Simulate real-time biometric updates
            $this->updateHeartRate(rand(60, 120), 'activity');
            sleep(30);
            
            $this->updateStressLevel(rand(20, 80));
            sleep(30);
            
            // Circadian rhythm check every hour
            if (date('i') === '00') {
                $this->circadianRhythmSupport();
            }
            
            sleep(60);
        }
    }

    private function initializeCircadianLighting(): void
    {
        echo "🌅 Initializing circadian rhythm lighting\n";
        $this->circadianRhythmSupport();
    }

    public function stopHealthMonitoring(): void
    {
        echo "\n🛑 Stopping health monitoring...\n";
        $this->monitoringActive = false;
        
        // Return to normal lighting
        foreach ($this->zones as $roomName => $room) {
            $room->setColor('#F0F8FF');
            $room->setBrightness(50);
            echo "  💡 {$roomName} returned to normal\n";
            usleep(300000);
        }
        
        echo "✅ Health monitoring stopped\n";
    }

    private function lightSleepLighting(): void
    {
        if (isset($this->zones['bedroom'])) {
            $this->zones['bedroom']->setColor('#2F2F4F');
            $this->zones['bedroom']->setBrightness(5);
        }
    }

    private function deepSleepLighting(): void
    {
        if (isset($this->zones['bedroom'])) {
            $this->zones['bedroom']->setColor('#000080');
            $this->zones['bedroom']->setBrightness(1);
        }
    }

    private function remSleepLighting(): void
    {
        if (isset($this->zones['bedroom'])) {
            $this->zones['bedroom']->setColor('#4B0082');
            $this->zones['bedroom']->setBrightness(3);
        }
    }

    private function wakePreparation(): void
    {
        echo "⏰ Wake preparation sequence\n";
        
        if (isset($this->zones['bedroom'])) {
            // Very gradual brightening 30 minutes before wake time
            $this->zones['bedroom']->sunrise(1800);
        }
    }

    private function heartEmergencyLighting(): void
    {
        echo "🚨 Heart arrhythmia detected\n";
        
        // Urgent but not overwhelming lighting
        for ($i = 0; $i < 8; $i++) {
            foreach ($this->zones as $zone) {
                $zone->setColor('#FF69B4'); // Hot pink for medical
                $zone->setBrightness(80);
            }
            usleep(750000);
            
            foreach ($this->zones as $zone) {
                $zone->setBrightness(20);
            }
            usleep(750000);
        }
    }

    private function seizureEmergencyLighting(): void
    {
        echo "🚨 Seizure detected - safe lighting mode\n";
        
        // Immediately stop all flashing and set steady, soft lighting
        foreach ($this->zones as $zone) {
            $zone->setColor('#FFFACD'); // Soft yellow
            $zone->setBrightness(30);    // Steady, non-triggering level
        }
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
    
    $health = new BiometricHealthIntegration($hue);
    
    echo "💊 Biometric Health Integration System\n";
    echo "=====================================\n\n";
    
    // Demo sleep tracking
    $health->sleepTrackingIntegration([
        'efficiency' => 82,
        'duration_hours' => 7.2,
        'deep_sleep_percent' => 18,
        'rem_percent' => 23
    ]);
    sleep(2);
    
    // Demo workout session
    echo "\n💪 Starting workout demo...\n";
    $health->workoutMode('cardio', 5); // 5-minute demo
    sleep(3);
    
    // Demo heart rate updates during workout
    $health->updateHeartRate(95, 'light_exercise');
    sleep(1);
    
    $health->updateHeartRate(140, 'moderate_exercise');
    sleep(1);
    
    $health->updateHeartRate(165, 'intense_exercise');
    sleep(2);
    
    // Demo meditation
    echo "\n🧘 Starting meditation demo...\n";
    $health->meditationMode(2); // 2-minute demo
    sleep(2);
    
    // Demo stress tracking
    $health->updateStressLevel(75);
    sleep(2);
    
    // Demo fitness goals
    $health->fitnessGoalProgress([
        'steps' => 10000,
        'calories' => 2000,
        'active_minutes' => 30,
        'water_intake' => 8
    ], [
        'steps' => 7500,
        'calories' => 1200,
        'active_minutes' => 25,
        'water_intake' => 4
    ]);
    sleep(3);
    
    // Demo circadian rhythm
    $health->circadianRhythmSupport();
    sleep(2);
    
    // Demo health metrics dashboard
    $health->healthMetricsDashboard();
    sleep(3);
    
    // Demo hydration reminder
    $health->hydrationReminder(3, 8);
    sleep(2);
    
    $health->stopHealthMonitoring();
    
} catch (Exception $e) {
    echo "❌ Health monitoring error: " . $e->getMessage() . "\n";
}

/*
Real-world Health Integration Examples:

// Apple Health/HealthKit Integration (via iOS app)
function syncWithAppleHealth($healthData) {
    return [
        'heart_rate' => $healthData['HKQuantityTypeIdentifierHeartRate'],
        'steps' => $healthData['HKQuantityTypeIdentifierStepCount'],
        'sleep' => $healthData['HKCategoryTypeIdentifierSleepAnalysis'],
        'workout' => $healthData['HKWorkoutTypeIdentifier']
    ];
}

// Fitbit API Integration
function fetchFitbitData($accessToken, $userId) {
    $endpoints = [
        'activities' => "https://api.fitbit.com/1/user/{$userId}/activities/date/today.json",
        'heart' => "https://api.fitbit.com/1/user/{$userId}/activities/heart/date/today/1d.json",
        'sleep' => "https://api.fitbit.com/1.2/user/{$userId}/sleep/date/today.json"
    ];
    
    $headers = ["Authorization: Bearer {$accessToken}"];
    
    $data = [];
    foreach ($endpoints as $type => $url) {
        $context = stream_context_create(['http' => ['header' => implode("\r\n", $headers)]]);
        $data[$type] = json_decode(file_get_contents($url, false, $context), true);
    }
    
    return $data;
}

// Garmin Connect IQ Integration
function processGarminData($garminWebhook) {
    $health->updateHeartRate($garminWebhook['heartRate']);
    $health->updateStressLevel($garminWebhook['stressLevel']);
    
    if ($garminWebhook['activityType'] === 'running') {
        $health->workoutMode('cardio', $garminWebhook['duration']);
    }
}

// Oura Ring API Integration
function fetchOuraData($accessToken) {
    $url = 'https://api.ouraring.com/v2/usercollection/daily_sleep';
    $headers = ["Authorization: Bearer {$accessToken}"];
    
    $context = stream_context_create(['http' => ['header' => implode("\r\n", $headers)]]);
    $sleepData = json_decode(file_get_contents($url, false, $context), true);
    
    return $sleepData;
}

// MQTT Integration for real-time health data
$mqtt = new PhpMqtt\Client\MqttClient($server, $port, $clientId);
$mqtt->connect();

$mqtt->subscribe('health/+/metrics', function ($topic, $message) use ($health) {
    $data = json_decode($message, true);
    
    if (isset($data['heart_rate'])) {
        $health->updateHeartRate($data['heart_rate'], $data['context'] ?? 'unknown');
    }
    
    if (isset($data['stress_level'])) {
        $health->updateStressLevel($data['stress_level']);
    }
});

// Google Fit API Integration
function fetchGoogleFitData($accessToken) {
    $dataSourceId = 'derived:com.google.heart_rate.bpm:com.google.android.gms:merge_heart_rate_bpm';
    $endTime = time() * 1000;
    $startTime = ($endTime - 86400000); // Last 24 hours
    
    $url = "https://www.googleapis.com/fitness/v1/users/me/dataSources/{$dataSourceId}/datasets/{$startTime}-{$endTime}";
    
    $context = stream_context_create([
        'http' => [
            'header' => "Authorization: Bearer {$accessToken}"
        ]
    ]);
    
    return json_decode(file_get_contents($url, false, $context), true);
}

// Samsung Health Integration (via SmartThings)
function samsungHealthWebhook($data) {
    if ($data['type'] === 'heart_rate') {
        $health->updateHeartRate($data['value']);
    }
    
    if ($data['type'] === 'sleep_session') {
        $health->sleepTrackingIntegration($data['sleep_data']);
    }
}

// Withings/Nokia Health Integration
function fetchWithingsData($accessToken, $userId) {
    $measureTypes = [
        1 => 'weight',
        4 => 'height', 
        9 => 'diastolic_bp',
        10 => 'systolic_bp',
        11 => 'heart_rate'
    ];
    
    $url = "https://wbsapi.withings.net/measure?action=getmeas&userid={$userId}&oauth_token={$accessToken}";
    return json_decode(file_get_contents($url), true);
}

// Home Assistant Health Integration
$homeAssistant->subscribeToEntity('sensor.xiaomi_mi_scale_weight', function($state) use ($health) {
    $health->weightTracking($state['state']);
});

$homeAssistant->subscribeToEntity('binary_sensor.motion_bedroom', function($state) use ($health) {
    if ($state['state'] === 'on' && date('H') >= 22) {
        $health->sleepCycleOptimization('bedtime_prep');
    }
});

// Emergency contact integration
function emergencyProtocol($healthEmergency) {
    // Send alert to emergency contacts
    $emergencyContacts = ['+1234567890', '+0987654321'];
    
    foreach ($emergencyContacts as $contact) {
        sendSMS($contact, "Health emergency detected at home. Lights activated for emergency responders.");
    }
    
    // Activate emergency lighting
    $health->healthEmergencyProtocol($healthEmergency['type'], $healthEmergency['data']);
}
*/