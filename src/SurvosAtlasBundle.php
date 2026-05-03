<?php

declare(strict_types=1);

namespace Survos\AtlasBundle;

use Survos\AtlasBundle\Command\AtlasExportCommand;
use Survos\AtlasBundle\Compiler\AtlasPass;
use Survos\AtlasBundle\Service\Atlas;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SurvosAtlasBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services()->defaults()->autowire()->autoconfigure();

        $services->set(Atlas::class)
            ->public()
            ->arg('$routes', [])
            ->arg('$entities', []);

        $services->set(AtlasExportCommand::class)
            ->arg('$atlas', new \Symfony\Component\DependencyInjection\Reference(Atlas::class))
            ->tag('console.command');
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new AtlasPass());
    }
}
