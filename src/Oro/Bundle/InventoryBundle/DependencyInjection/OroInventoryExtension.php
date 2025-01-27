<?php

namespace Oro\Bundle\InventoryBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class OroInventoryExtension extends Extension
{
    const VALIDATION_CONFIG = 'oro_inventory.validation.config_path';

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->prependExtensionConfig($this->getAlias(), $config);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $loader->load('services.yml');
        $loader->load('services_api.yml');
        $loader->load('form_types.yml');
        $loader->load('importexport.yml');
        $loader->load('event_listeners.yml');
        $loader->load('controllers.yml');

        $container->setParameter(
            self::VALIDATION_CONFIG,
            __DIR__ . '/../Resources/config/validation_inventory_level.yml'
        );
    }
}
