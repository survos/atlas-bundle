# Atlas Bundle

Compile-time discovery of controllers, routes, and entities — the atlas the rest of survos draws maps from.

```bash
composer require survos/atlas-bundle
```

## What it does

At compile time, it walks:

1. **Controllers** — every service tagged `container.service_subscriber` (the Symfony default for `AbstractController` subclasses), recording one `RouteEntry` per `#[Route]` method along with every other attribute on the method and class.
2. **Entities** — directories from `doctrine.orm.mappings` plus `src/Entity` plus each registered bundle's `Entity/` subdir, recording one `EntityEntry` per concrete class with its class-level attributes.

The result is exposed as a runtime `Atlas` service. Attribute payloads are stored as `{class, args}` pairs, not instantiated objects, so the snapshot is cheap to serialize and survives even if an attribute class fails to load later.

## Using the Atlas at runtime

```php
use Survos\AtlasBundle\Service\Atlas;

final class MyService
{
    public function __construct(private readonly Atlas $atlas) {}

    public function example(): void
    {
        foreach ($this->atlas->routes() as $route) {
            // $route->name, $route->path, $route->methodAttributes, ...
        }

        $hits = $this->atlas->routesWithAttribute(\App\Attribute\PublicApi::class);
    }
}
```

## Using the builders inside your own compiler pass

```php
use Survos\AtlasBundle\Compiler\ControllerAtlasBuilder;
use Survos\AtlasBundle\Compiler\EntityAtlasBuilder;

final class MyBundlePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach (ControllerAtlasBuilder::build($container) as $route) {
            foreach ($route->attributesOf(\App\Attribute\Foo::class) as $hit) {
                // $hit['args'] holds the original attribute arguments
            }
        }

        foreach (EntityAtlasBuilder::build($container, extraDirs: ['/path/to/extra']) as $entity) {
            // ...
        }
    }
}
```

The builders are pure helpers — no service registration, no runtime cost when called from a compile pass.

## Console

```bash
bin/console atlas:export                    # JSON to stdout
bin/console atlas:export --pretty           # pretty JSON
bin/console atlas:export -f yaml            # YAML
bin/console atlas:export -o atlas.json      # write to file
```

The output is the natural shape to paste into an LLM conversation when you want to ask design questions about your application's surface area.

## Conventions

- A `#[Route]` without an explicit `name:` is skipped. Atlas needs a stable identifier and won't guess.
- Multiple `#[Route]` attributes on the same method each produce their own `RouteEntry`.
- Entity discovery skips abstract classes, interfaces, and traits.
