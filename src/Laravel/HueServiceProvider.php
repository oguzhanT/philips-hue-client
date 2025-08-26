<?php

namespace OguzhanTogay\HueClient\Laravel;

use Illuminate\Support\ServiceProvider;
use OguzhanTogay\HueClient\HueClient;

class HueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HueClient::class, function ($app) {
            $config = $app['config']['services.hue'];
            
            return new HueClient(
                $config['bridge_ip'] ?? '',
                $config['username'] ?? null,
                $config['options'] ?? []
            );
        });

        $this->app->alias(HueClient::class, 'hue');
    }

    public function boot(): void
    {
        // Publish configuration if needed
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/hue.php' => config_path('hue.php'),
            ], 'hue-config');
        }
    }
}