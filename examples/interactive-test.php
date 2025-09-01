#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Exceptions\HueException;

$bridgeIp = $argv[1] ?? null;
$username = $argv[2] ?? null;

if (!$bridgeIp) {
    echo "Kullanım: php interactive-test.php <bridge_ip> [username]\n";
    echo "Örnek: php interactive-test.php 192.168.1.100\n";
    echo "       php interactive-test.php 192.168.1.100 your-username\n\n";
    
    echo "Bridge IP bulamak için: https://discovery.meethue.com/\n";
    exit(1);
}

try {
    echo "Philips Hue Client Test\n";
    echo "========================\n\n";
    
    $client = new HueClient($bridgeIp, $username);
    
    if (!$username) {
        echo "Username belirtilmedi. Yeni kullanıcı oluşturulacak.\n";
        echo "Lütfen Hue Bridge üzerindeki butona basın ve Enter'a basın...";
        readline();
        
        try {
            $username = $client->register('test-app', 'test-device');
            echo "Yeni kullanıcı oluşturuldu!\n";
            echo "Username: " . $username . "\n\n";
            
            echo "Bu bilgileri kaydedin ve sonraki çalıştırmalarda kullanın:\n";
            echo "php interactive-test.php $bridgeIp " . $username . "\n\n";
            
            $client->setUsername($username);
        } catch (HueException $e) {
            echo "Hata: " . $e->getMessage() . "\n";
            echo "Bridge üzerindeki butona bastığınızdan emin olun!\n";
            exit(1);
        }
    }
    
    echo "Bridge'e bağlanılıyor...\n\n";
    
    // Lights listesi
    echo "=== IŞIKLAR ===\n";
    try {
        $lights = $client->lights()->getAll();
        if (empty($lights)) {
            echo "Hiç ışık bulunamadı.\n";
        } else {
            foreach ($lights as $id => $light) {
                $state = $light->getState();
                echo sprintf(
                    "ID: %s | %s | Durum: %s | Parlaklık: %d%% | Renk: %s\n",
                    $light->getId(),
                    $light->getName(),
                    $state->isOn() ? 'Açık' : 'Kapalı',
                    round(($state->getBrightness() ?? 0) / 254 * 100),
                    $state->getXY() ? 'xy: ' . json_encode($state->getXY()) : 'N/A'
                );
            }
        }
    } catch (\Exception $e) {
        echo "Işıklar alınamadı: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== GRUPLAR ===\n";
    try {
        $groups = $client->groups()->getAll();
        if (empty($groups)) {
            echo "Hiç grup bulunamadı.\n";
        } else {
            foreach ($groups as $id => $group) {
                echo sprintf(
                    "ID: %s | %s | Tip: %s | Işık sayısı: %d\n",
                    $group->getId(),
                    $group->getName(),
                    $group->getType(),
                    count($group->getLights())
                );
            }
        }
    } catch (\Exception $e) {
        echo "Gruplar alınamadı: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== SAHNE (SCENES) ===\n";
    try {
        $scenes = $client->scenes()->getAll();
        if (empty($scenes)) {
            echo "Hiç sahne bulunamadı.\n";
        } else {
            $count = 0;
            foreach ($scenes as $id => $scene) {
                if ($count++ >= 5) {
                    echo "... ve " . (count($scenes) - 5) . " sahne daha\n";
                    break;
                }
                echo sprintf(
                    "ID: %s | %s\n",
                    substr($scene->getId(), 0, 8),
                    $scene->getName()
                );
            }
        }
    } catch (\Exception $e) {
        echo "Sahneler alınamadı: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== İNTERAKTİF TEST ===\n";
    echo "Komutlar:\n";
    echo "  on <light_id>    - Işığı aç\n";
    echo "  off <light_id>   - Işığı kapat\n";
    echo "  bri <light_id> <0-100> - Parlaklık ayarla (%)\n";
    echo "  color <light_id> <r> <g> <b> - Renk ayarla (RGB)\n";
    echo "  scene <scene_id> - Sahneyi etkinleştir\n";
    echo "  exit - Çıkış\n\n";
    
    while (true) {
        $input = readline("Komut: ");
        if ($input === 'exit') break;
        
        $parts = explode(' ', $input);
        $command = $parts[0] ?? '';
        
        try {
            switch ($command) {
                case 'on':
                    $lightId = $parts[1] ?? null;
                    if (!$lightId) {
                        echo "Kullanım: on <light_id>\n";
                        break;
                    }
                    $light = $client->lights()->get($lightId);
                    $light->setState(['on' => true]);
                    echo "Işık $lightId açıldı.\n";
                    break;
                    
                case 'off':
                    $lightId = $parts[1] ?? null;
                    if (!$lightId) {
                        echo "Kullanım: off <light_id>\n";
                        break;
                    }
                    $light = $client->lights()->get($lightId);
                    $light->setState(['on' => false]);
                    echo "Işık $lightId kapatıldı.\n";
                    break;
                    
                case 'bri':
                    $lightId = $parts[1] ?? null;
                    $brightness = $parts[2] ?? null;
                    if (!$lightId || !is_numeric($brightness)) {
                        echo "Kullanım: bri <light_id> <0-100>\n";
                        break;
                    }
                    $briValue = round($brightness / 100 * 254);
                    $light = $client->lights()->get($lightId);
                    $light->setState(['bri' => $briValue]);
                    echo "Işık $lightId parlaklığı %$brightness olarak ayarlandı.\n";
                    break;
                    
                case 'color':
                    $lightId = $parts[1] ?? null;
                    $r = $parts[2] ?? null;
                    $g = $parts[3] ?? null;
                    $b = $parts[4] ?? null;
                    if (!$lightId || !is_numeric($r) || !is_numeric($g) || !is_numeric($b)) {
                        echo "Kullanım: color <light_id> <r> <g> <b>\n";
                        break;
                    }
                    
                    // RGB to XY conversion (basit yaklaşım)
                    $red = $r / 255;
                    $green = $g / 255;
                    $blue = $b / 255;
                    
                    $X = $red * 0.664511 + $green * 0.154324 + $blue * 0.162028;
                    $Y = $red * 0.283881 + $green * 0.668433 + $blue * 0.047685;
                    $Z = $red * 0.000088 + $green * 0.072310 + $blue * 0.986039;
                    
                    if (($X + $Y + $Z) > 0) {
                        $x = $X / ($X + $Y + $Z);
                        $y = $Y / ($X + $Y + $Z);
                        $light = $client->lights()->get($lightId);
                        $light->setState(['xy' => [$x, $y]]);
                        echo "Işık $lightId rengi RGB($r, $g, $b) olarak ayarlandı.\n";
                    } else {
                        echo "Geçersiz renk değerleri.\n";
                    }
                    break;
                    
                case 'scene':
                    $sceneId = $parts[1] ?? null;
                    if (!$sceneId) {
                        echo "Kullanım: scene <scene_id>\n";
                        break;
                    }
                    $scene = $client->scenes()->get($sceneId);
                    $scene->activate();
                    echo "Sahne $sceneId etkinleştirildi.\n";
                    break;
                    
                default:
                    echo "Bilinmeyen komut: $command\n";
            }
        } catch (HueException $e) {
            echo "Hata: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nTest tamamlandı!\n";
    
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage() . "\n";
    exit(1);
}