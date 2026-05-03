<?php

declare(strict_types=1);

namespace Survos\AtlasBundle\Service;

use Survos\AtlasBundle\Model\EntityEntry;
use Survos\AtlasBundle\Model\RouteEntry;

/**
 * Runtime snapshot of everything the AtlasPass discovered at compile time.
 * Consumers ask the Atlas for routes/entities; nothing is rescanned at runtime.
 *
 * The compiled container stores entries as plain arrays (XmlDumper refuses
 * arbitrary objects); the constructor reconstitutes them into typed DTOs.
 * Cost is a single array_map at boot — sub-millisecond for typical apps.
 */
final class Atlas
{
    /** @var list<RouteEntry> */
    private readonly array $routes;

    /** @var list<EntityEntry> */
    private readonly array $entities;

    /** @var array<string, RouteEntry> */
    private readonly array $routesByName;

    /** @var array<class-string, EntityEntry> */
    private readonly array $entitiesByClass;

    /**
     * @param list<array<string, mixed>> $routes
     * @param list<array<string, mixed>> $entities
     */
    public function __construct(
        array $routes = [],
        array $entities = [],
    ) {
        $this->routes = array_map(static fn (array $r) => new RouteEntry(
            name:             $r['name'],
            path:             $r['path'],
            methods:          $r['methods'],
            controllerClass:  $r['controllerClass'],
            methodName:       $r['methodName'],
            methodAttributes: $r['methodAttributes'],
            classAttributes:  $r['classAttributes'],
        ), $routes);

        $this->entities = array_map(static fn (array $e) => new EntityEntry(
            fqcn:            $e['fqcn'],
            shortName:       $e['shortName'],
            classAttributes: $e['classAttributes'],
        ), $entities);

        $byName = [];
        foreach ($this->routes as $r) {
            $byName[$r->name] = $r;
        }
        $this->routesByName = $byName;

        $byClass = [];
        foreach ($this->entities as $e) {
            $byClass[$e->fqcn] = $e;
        }
        $this->entitiesByClass = $byClass;
    }

    /** @return list<RouteEntry> */
    public function routes(): array
    {
        return $this->routes;
    }

    /** @return list<EntityEntry> */
    public function entities(): array
    {
        return $this->entities;
    }

    public function route(string $name): ?RouteEntry
    {
        return $this->routesByName[$name] ?? null;
    }

    public function entity(string $fqcn): ?EntityEntry
    {
        return $this->entitiesByClass[$fqcn] ?? null;
    }

    /**
     * All routes whose method (or controller class) carries the given attribute.
     *
     * @return list<RouteEntry>
     */
    public function routesWithAttribute(string $attributeFqcn): array
    {
        return array_values(array_filter(
            $this->routes,
            static fn (RouteEntry $r) => $r->hasAttribute($attributeFqcn),
        ));
    }

    /**
     * @return list<EntityEntry>
     */
    public function entitiesWithAttribute(string $attributeFqcn): array
    {
        return array_values(array_filter(
            $this->entities,
            static fn (EntityEntry $e) => $e->hasAttribute($attributeFqcn),
        ));
    }
}
