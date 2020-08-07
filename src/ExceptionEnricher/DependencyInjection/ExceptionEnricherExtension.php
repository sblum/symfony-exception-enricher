<?php

declare(strict_types=1);

namespace ExceptionEnricher\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ExceptionEnricherExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $locator = new FileLocator(\dirname(__DIR__).'/Resources/config');
        $loader = new YamlFileLoader($container, $locator);

        $loader->load('services.yaml');
    }
}
