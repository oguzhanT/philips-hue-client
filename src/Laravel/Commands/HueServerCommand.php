<?php

namespace OguzhanTogay\HueClient\Laravel\Commands;

use Illuminate\Console\Command;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Api\RestApi;

class HueServerCommand extends Command
{
    protected $signature = 'hue:serve 
                           {--port=8080 : Server port}
                           {--host=localhost : Server host}';

    protected $description = 'Start the Hue REST API server';

    public function handle(): int
    {
        $port = $this->option('port');
        $host = $this->option('host');

        try {
            $hueClient = app(HueClient::class);

            if (!$hueClient->isConnected()) {
                $this->error('❌ Cannot connect to Hue Bridge');
                $this->line('💡 Run: php artisan hue:setup');
                return self::FAILURE;
            }

            $this->info('🚀 Starting Hue REST API Server...');
            $this->line("🌉 Bridge: {$hueClient->getBridgeIp()}");
            $this->line("🌐 Server: http://{$host}:{$port}");
            $this->line("📚 Documentation: http://{$host}:{$port}/docs");
            $this->line("🔧 API Base: http://{$host}:{$port}/api");
            $this->newLine();
            $this->info('✅ Server running... Press Ctrl+C to stop');

            // Set environment variables
            putenv("HUE_BRIDGE_IP={$hueClient->getBridgeIp()}");
            putenv("HUE_USERNAME={$hueClient->getUsername()}");

            // Start built-in PHP server
            // The package's own public/ (the vendor path changed with the package name).
            $publicPath = dirname(__DIR__, 3) . '/public';
            $router = $publicPath . '/api.php';

            $command = sprintf(
                'php -S %s -t %s %s',
                escapeshellarg("{$host}:{$port}"),
                escapeshellarg($publicPath),
                escapeshellarg($router)
            );
            passthru($command);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Server failed to start: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
