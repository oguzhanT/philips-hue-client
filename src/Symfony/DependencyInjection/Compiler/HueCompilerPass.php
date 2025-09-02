<?php

namespace OguzhanTogay\HueClient\Symfony\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class HueCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Auto-configure services if needed
        if (!$container->hasDefinition('hue.client')) {
            return;
        }

        // Set up connection pool with all configured bridges
        if ($container->hasDefinition('hue.connection_pool')) {
            $poolDefinition = $container->getDefinition('hue.connection_pool');
            $config = $container->getParameter('hue.config') ?? [];

            if (isset($config['bridges'])) {
                foreach ($config['bridges'] as $name => $bridgeConfig) {
                    if (isset($bridgeConfig['ip'], $bridgeConfig['username'])) {
                        $poolDefinition->addMethodCall('addBridge', [
                            $bridgeConfig['ip'],
                            $bridgeConfig['username']
                        ]);
                    }
                }
            }
        }

        // Configure cache adapters
        $this->configureCacheAdapters($container);
    }

    private function configureCacheAdapters(ContainerBuilder $container): void
    {
        $config = $container->getParameter('hue.config') ?? [];
        $cacheConfig = $config['cache'] ?? [];

        if (isset($cacheConfig['adapter']) && $cacheConfig['adapter'] === 'redis') {
            // Ensure Redis cache is properly configured
            if (!$container->hasDefinition('cache.adapter.redis')) {
                // Add Redis cache adapter if not already defined
                $container->register('cache.adapter.redis', 'Symfony\Component\Cache\Adapter\RedisAdapter');
            }
        }
    }
}
