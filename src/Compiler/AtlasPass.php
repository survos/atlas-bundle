<?php

declare(strict_types=1);

namespace Survos\AtlasBundle\Compiler;

use Survos\AtlasBundle\Model\EntityEntry;
use Survos\AtlasBundle\Model\RouteEntry;
use Survos\AtlasBundle\Service\Atlas;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Runs the controller and entity builders and stores the result on the
 * Atlas service as plain arrays. Atlas's constructor reconstitutes the
 * RouteEntry / EntityEntry DTOs at boot — sub-millisecond cost, and it
 * keeps the dumped container free of objects that XmlDumper rejects.
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
            ->setArgument('$routes',   array_map(self::routeToArray(...),  $routes))
            ->setArgument('$entities', array_map(self::entityToArray(...), $entities));

        $container->setParameter('survos_atlas.route_count', \count($routes));
        $container->setParameter('survos_atlas.entity_count', \count($entities));
    }

    /** @return array<string, mixed> */
    private static function routeToArray(RouteEntry $r): array
    {
        return [
            'name'             => $r->name,
            'path'             => $r->path,
            'methods'          => $r->methods,
            'controllerClass'  => $r->controllerClass,
            'methodName'       => $r->methodName,
            'methodAttributes' => $r->methodAttributes,
            'classAttributes'  => $r->classAttributes,
        ];
    }

    /** @return array<string, mixed> */
    private static function entityToArray(EntityEntry $e): array
    {
        return [
            'fqcn'            => $e->fqcn,
            'shortName'       => $e->shortName,
            'classAttributes' => $e->classAttributes,
        ];
    }
}
