<?php

namespace OguzhanTogay\HueClient\Laravel\Commands;

use Illuminate\Console\Command;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;

class HueSetupCommand extends Command
{
    protected $signature = 'hue:setup 
                           {--ip= : Bridge IP address}
                           {--discover : Auto-discover bridge}';

    protected $description = 'Setup and authenticate with Philips Hue bridge';

    public function handle(): int
    {
        $bridgeIp = $this->option('ip');
        $discover = $this->option('discover');

        // Auto-discover if no IP provided or discover flag set
        if (!$bridgeIp || $discover) {
            $this->info('🔍 Discovering bridges...');
            $discovery = new BridgeDiscovery();
            $bridges = $discovery->discover();

            if (empty($bridges)) {
                $this->error('❌ No bridges found');
                return self::FAILURE;
            }

            $bridgeIp = $bridges[0]->getIp();
            $this->info("✅ Found bridge at: {$bridgeIp}");
        }

        if (!$bridgeIp) {
            $this->error('❌ No bridge IP provided');
            $this->line('💡 Use --ip option or --discover flag');
            return self::FAILURE;
        }

        try {
            $this->info('🔗 Connecting to bridge...');
            $client = new HueClient($bridgeIp);

            $this->warn('⚠️  Press the link button on your Hue Bridge now!');
            
            if (!$this->confirm('Press Enter when you have pressed the link button')) {
                $this->info('Setup cancelled');
                return self::FAILURE;
            }

            $this->info('🔐 Registering application...');
            $username = $client->register('laravel-hue-app', 'laravel-' . gethostname());

            $this->info('✅ Successfully registered with bridge!');
            $this->newLine();
            
            $this->info('📝 Add these to your .env file:');
            $this->line("HUE_BRIDGE_IP={$bridgeIp}");
            $this->line("HUE_USERNAME={$username}");
            
            $this->newLine();
            $this->info('🎉 Setup complete! You can now use the Hue facade in your Laravel app');
            $this->line('Example: Hue::lights()->getAll()');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Setup failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}