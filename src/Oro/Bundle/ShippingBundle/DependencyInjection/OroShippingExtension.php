<?php

namespace Oro\Bundle\ShippingBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class OroShippingExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->prependExtensionConfig($this->getAlias(), $config);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('services_api.yml');
        $loader->load('form_types.yml');
        $loader->load('shipping_methods.yml');
        $loader->load('mass_action.yml');
        $loader->load('block_types.yml');
        $loader->load('method_event_listeners.yml');
        $loader->load('controllers.yml');
        $loader->load('controllers_api.yml');
    }
}
