<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\Bundle\CoreBundle\DependencyInjection;

use Sylius\Bundle\CoreBundle\Migrations\Utility\TopologyBasedVersionComparator;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SyliusCoreExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    /** @var array */
    private static $bundles = [
        'sylius_addressing',
        'sylius_attribute',
        'sylius_channel',
        'sylius_currency',
        'sylius_customer',
        'sylius_inventory',
        'sylius_locale',
        'sylius_order',
        'sylius_payment',
        'sylius_payum',
        'sylius_product',
        'sylius_promotion',
        'sylius_review',
        'sylius_shipping',
        'sylius_taxation',
        'sylius_taxonomy',
        'sylius_user',
        'sylius_variation',
    ];

    /**
     * {@inheritdoc}
     */
    public function load(array $config, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration([], $container), $config);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $this->registerResources('sylius', $config['driver'], $config['resources'], $container);

        $loader->load('services.xml');

        $env = $container->getParameter('kernel.environment');
        if ('test' === $env || 'test_cached' === $env) {
            $loader->load('test_services.xml');
        }

        $migrationsPackages = array_replace($config['migrations'] ?? [], ['Sylius\Bundle\CoreBundle\Migrations' => []]);
        $container->getDefinition(TopologyBasedVersionComparator::class)->setArgument(0, $migrationsPackages);
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container): void
    {
        $config = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration($this->getConfiguration([], $container), $config);

        $this->prependSyliusThemeBundle($container, $config['driver']);
        $this->prependHwiOauth($container);
        $this->prependDoctrineMigrations($container);
    }

    private function prependHwiOauth(ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        if (!$container->hasExtension('hwi_oauth')) {
            return;
        }

        $loader->load('services/integrations/hwi_oauth.xml');
    }

    private function prependSyliusThemeBundle(ContainerBuilder $container, string $driver): void
    {
        foreach ($container->getExtensions() as $name => $extension) {
            if (in_array($name, self::$bundles, true)) {
                $container->prependExtensionConfig($name, ['driver' => $driver]);
            }
        }

        $container->prependExtensionConfig('sylius_theme', ['context' => 'sylius.theme.context.channel_based']);
    }

    private function prependDoctrineMigrations(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine_migrations')) {
            return;
        }

        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'Sylius\Bundle\CoreBundle\Migrations' => __DIR__ . '/../Migrations',
            ],
        ]);
    }
}
