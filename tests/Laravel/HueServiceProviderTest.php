<?php

namespace OguzhanTogay\HueClient\Tests\Laravel;

use Orchestra\Testbench\TestCase;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\ConnectionPool;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;
use OguzhanTogay\HueClient\Laravel\HueServiceProvider;
use OguzhanTogay\HueClient\Laravel\Facades\Hue;

class HueServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            HueServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Hue' => Hue::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('hue.bridges.main.ip', '192.168.1.100');
        $app['config']->set('hue.bridges.main.username', 'test-username');
        $app['config']->set('hue.auto_discovery', false);
    }

    public function testServiceProviderRegistersHueClient(): void
    {
        $hueClient = $this->app->make(HueClient::class);
        $this->assertInstanceOf(HueClient::class, $hueClient);
        $this->assertEquals('192.168.1.100', $hueClient->getBridgeIp());
        $this->assertEquals('test-username', $hueClient->getUsername());
    }

    public function testServiceProviderRegistersConnectionPool(): void
    {
        $pool = $this->app->make(ConnectionPool::class);
        $this->assertInstanceOf(ConnectionPool::class, $pool);
    }

    public function testServiceProviderRegistersBridgeDiscovery(): void
    {
        $discovery = $this->app->make(BridgeDiscovery::class);
        $this->assertInstanceOf(BridgeDiscovery::class, $discovery);
    }

    public function testHueFacadeWorks(): void
    {
        $this->assertTrue($this->app->bound('hue'));
        
        $hueClient = Hue::getFacadeRoot();
        $this->assertInstanceOf(HueClient::class, $hueClient);
    }

    public function testConfigurationIsPublished(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'hue-config'])
            ->assertExitCode(0);
            
        $this->assertFileExists(config_path('hue.php'));
    }

    public function testArtisanCommandsAreRegistered(): void
    {
        $this->artisan('list')
            ->expectsOutput('hue:discover')
            ->expectsOutput('hue:setup')
            ->expectsOutput('hue:serve');
    }

    public function testHueDiscoverCommand(): void
    {
        $this->artisan('hue:discover', ['--timeout' => 1])
            ->assertExitCode(1); // Will fail because no real bridge in test
    }

    public function testMultipleBridgeConfiguration(): void
    {
        $this->app['config']->set('hue.bridges.secondary', [
            'ip' => '192.168.1.101',
            'username' => 'test-username-2'
        ]);

        $pool = $this->app->make(ConnectionPool::class);
        $this->assertInstanceOf(ConnectionPool::class, $pool);
        $this->assertEquals(2, $pool->getBridgeCount());
    }
}