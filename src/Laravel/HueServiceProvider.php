<?php

namespace OguzhanTogay\HueClient\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\ConnectionPool;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;
use OguzhanTogay\HueClient\Laravel\Commands\HueDiscoverCommand;
use OguzhanTogay\HueClient\Laravel\Commands\HueSetupCommand;
use OguzhanTogay\HueClient\Laravel\Commands\HueServerCommand;

class HueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/hue.php',
            'hue'
        );

        // Register main HueClient
        $this->app->singleton(HueClient::class, function (Application $app) {
            $config = $app['config']['hue'];
            $bridgeConfig = $config['bridges'][$config['default']] ?? $config['bridges']['main'];
            
            if (!$bridgeConfig['ip'] && $config['auto_discovery']) {
                $discovery = new BridgeDiscovery();
                $bridges = $discovery->discover();
                if (!empty($bridges)) {
                    $bridgeConfig['ip'] = $bridges[0]->getIp();
                }
            }

            if (!$bridgeConfig['ip']) {
                throw new \InvalidArgumentException('Hue Bridge IP not configured and auto-discovery failed');
            }

            $logger = $config['logging']['enabled'] ? $app['log'] : null;

            return new HueClient(
                $bridgeConfig['ip'],
                $bridgeConfig['username'],
                $bridgeConfig['options'] ?? [],
                $logger
            );
        });

        // Register Connection Pool for multiple bridges
        $this->app->singleton(ConnectionPool::class, function (Application $app) {
            $config = $app['config']['hue'];
            $pool = new ConnectionPool();

            foreach ($config['bridges'] as $name => $bridgeConfig) {
                if ($bridgeConfig['ip'] && $bridgeConfig['username']) {
                    $pool->addBridge($bridgeConfig['ip'], $bridgeConfig['username']);
                }
            }

            return $pool;
        });

        // Register Bridge Discovery
        $this->app->singleton(BridgeDiscovery::class, function () {
            return new BridgeDiscovery();
        });

        // Aliases
        $this->app->alias(HueClient::class, 'hue');
        $this->app->alias(ConnectionPool::class, 'hue.pool');
        $this->app->alias(BridgeDiscovery::class, 'hue.discovery');
    }

    public function boot(): void
    {
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/hue.php' => config_path('hue.php'),
            ], 'hue-config');

            $this->publishes([
                __DIR__ . '/../../config/hue.php' => config_path('hue.php'),
            ], 'hue');

            // Register Artisan commands
            $this->commands([
                HueDiscoverCommand::class,
                HueSetupCommand::class,
                HueServerCommand::class,
            ]);
        }

        // Register event listeners if events are enabled
        $config = $this->app['config']['hue'];
        if ($config['events']['enabled']) {
            $this->registerEventListeners();
        }
    }

    public function provides(): array
    {
        return [
            HueClient::class,
            ConnectionPool::class,
            BridgeDiscovery::class,
            'hue',
            'hue.pool',
            'hue.discovery'
        ];
    }

    private function registerEventListeners(): void
    {
        // Register any event listeners for Hue events
        // This could integrate with Laravel's event system
    }
}