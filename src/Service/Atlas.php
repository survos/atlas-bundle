<?php

declare(strict_types=1);

namespace Survos\AtlasBundle\Service;

use Survos\AtlasBundle\Model\EntityEntry;
use Survos\AtlasBundle\Model\RouteEntry;

/**
 * Runtime snapshot of everything the AtlasPass discovered at compile time.
 * Consumers ask the Atlas for routes/entities; nothing is rescanned at runtime.
 */
final class Atlas
{
    /** @var array<string, RouteEntry> */
    private readonly array $routesByName;

    /** @var array<class-string, EntityEntry> */
    private readonly array $entitiesByClass;

    /**
     * @param list<RouteEntry>  $routes
     * @param list<EntityEntry> $entities
     */
    public function __construct(
        private readonly array $routes = [],
        private readonly array $entities = [],
    ) {
        $byName = [];
        foreach ($routes as $r) {
            $byName[$r->name] = $r;
        }
        $this->routesByName = $byName;

        $byClass = [];
        foreach ($entities as $e) {
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
