<?php

declare(strict_types=1);

namespace Survos\AtlasBundle\Compiler;

use Survos\AtlasBundle\Service\Atlas;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Runs the controller and entity builders and stuffs the result into the
 * Atlas service. Other bundles' compiler passes can either:
 *
 *   - read the Atlas service definition's arguments at compile time
 *   - or read the survos_atlas.routes / survos_atlas.entities parameters
 *   - or call the builders directly themselves (cheap; just reflection)
 */
final class AtlasPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(Atlas::class)) {
            return;
        }

        $routes   = ControllerAtlasBuilder::build($container);
        $entities = EntityAtlasBuilder::build($container);

        $container->getDefinition(Atlas::class)
            ->setArgument('$routes', $routes)
            ->setArgument('$entities', $entities);

        $container->setParameter('survos_atlas.route_count', \count($routes));
        $container->setParameter('survos_atlas.entity_count', \count($entities));
    }
}
