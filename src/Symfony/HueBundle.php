<?php

namespace OguzhanTogay\HueClient\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use OguzhanTogay\HueClient\Symfony\DependencyInjection\HueExtension;
use OguzhanTogay\HueClient\Symfony\DependencyInjection\Compiler\HueCompilerPass;

class HueBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new HueCompilerPass());
    }

    public function getContainerExtension(): HueExtension
    {
        return new HueExtension();
    }
}