<?php

namespace OguzhanTogay\HueClient\Laravel\Commands;

use Illuminate\Console\Command;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;

class HueDiscoverCommand extends Command
{
    protected $signature = 'hue:discover 
                           {--timeout=5 : Discovery timeout in seconds}';

    protected $description = 'Discover Philips Hue bridges on the network';

    public function handle(): int
    {
        $this->info('🔍 Discovering Philips Hue bridges...');
        
        $timeout = (int) $this->option('timeout');
        $discovery = new BridgeDiscovery();
        
        try {
            $bridges = $discovery->discover($timeout);
            
            if (empty($bridges)) {
                $this->error('❌ No Hue bridges found on your network');
                $this->line('💡 Make sure your bridge is connected and powered on');
                return self::FAILURE;
            }

            $this->info("✅ Found " . count($bridges) . " bridge(s):");
            $this->newLine();

            $headers = ['ID', 'IP Address', 'Name', 'Model'];
            $rows = [];

            foreach ($bridges as $bridge) {
                $rows[] = [
                    $bridge->getId(),
                    $bridge->getIp(),
                    $bridge->getName() ?? 'Unknown',
                    $bridge->getModelId() ?? 'Unknown'
                ];
            }

            $this->table($headers, $rows);
            
            $this->newLine();
            $this->info('💡 To use a bridge, add these to your .env file:');
            $this->line("HUE_BRIDGE_IP={$bridges[0]->getIp()}");
            $this->line('HUE_USERNAME=<run php artisan hue:setup to get username>');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Discovery failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}