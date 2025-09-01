<?php

namespace OguzhanTogay\HueClient\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\ConnectionPool;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;

class HueExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Load services
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../Resources/config'));
        $loader->load('services.yaml');

        // Register HueClient
        $this->registerHueClient($container, $config);
        
        // Register ConnectionPool if multiple bridges configured
        $this->registerConnectionPool($container, $config);
        
        // Register BridgeDiscovery
        $this->registerBridgeDiscovery($container, $config);

        // Register commands if console component is available
        if (class_exists('Symfony\Component\Console\Command\Command')) {
            $this->registerCommands($container);
        }
    }

    private function registerHueClient(ContainerBuilder $container, array $config): void
    {
        $defaultBridge = $config['default_bridge'] ?? 'main';
        $bridgeConfig = $config['bridges'][$defaultBridge] ?? $config['bridges']['main'];

        $definition = new Definition(HueClient::class);
        $definition->setArguments([
            $bridgeConfig['ip'],
            $bridgeConfig['username'],
            $bridgeConfig['options'] ?? [],
            new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE)
        ]);
        $definition->setPublic(true);

        $container->setDefinition('hue.client', $definition);
        $container->setAlias(HueClient::class, 'hue.client');
    }

    private function registerConnectionPool(ContainerBuilder $container, array $config): void
    {
        $definition = new Definition(ConnectionPool::class);
        $definition->setArguments([
            $config['bridges'] ?? [],
            $config['connection_pool']['max_connections'] ?? 10,
            $config['connection_pool']['timeout'] ?? 5,
            new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE)
        ]);
        $definition->setPublic(true);

        $container->setDefinition('hue.connection_pool', $definition);
        $container->setAlias(ConnectionPool::class, 'hue.connection_pool');
    }

    private function registerBridgeDiscovery(ContainerBuilder $container, array $config): void
    {
        $definition = new Definition(BridgeDiscovery::class);
        $definition->setArguments([
            $config['discovery']['timeout'] ?? 5
        ]);
        $definition->setPublic(true);

        $container->setDefinition('hue.discovery', $definition);
        $container->setAlias(BridgeDiscovery::class, 'hue.discovery');
    }

    private function registerCommands(ContainerBuilder $container): void
    {
        // Register console commands
        $commands = [
            'OguzhanTogay\HueClient\Symfony\Command\HueDiscoverCommand',
            'OguzhanTogay\HueClient\Symfony\Command\HueSetupCommand',
            'OguzhanTogay\HueClient\Symfony\Command\HueServerCommand',
        ];

        foreach ($commands as $commandClass) {
            $definition = new Definition($commandClass);
            $definition->addTag('console.command');
            $definition->setAutowired(true);
            $container->setDefinition($commandClass, $definition);
        }
    }
}