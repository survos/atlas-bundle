# Atlas Bundle

Compile-time discovery of controllers, routes, and entities — the atlas the rest of survos draws maps from.

```bash
composer require survos/atlas-bundle
```

## What it does

At compile time, it walks:

1. **Controllers** — every service tagged `container.service_subscriber` (the Symfony default for `AbstractController` subclasses), recording one `RouteEntry` per `#[Route]` method along with every other attribute on the method and class.
2. **Entities** — directories from `doctrine.orm.mappings` plus `src/Entity` plus each registered bundle's `Entity/` subdir, recording one `EntityEntry` per concrete class with its class-level attributes.

The result is exposed as a runtime `Atlas` service. Atlas does not interpret attributes — it records them. Bundles like [`survos/field-bundle`](#see-also) layer domain meaning (`#[EntityMeta]`, `#[RouteMeta]`, …) on top.

## How attributes are stored — the `{class, args}` shape

Attribute payloads are kept as plain arrays, not instantiated objects:

```php
[
    'class' => 'App\Attribute\PublicApi',
    'args'  => ['version' => 2, 'description' => 'Public list endpoint'],
]
```

`args` is exactly what `ReflectionAttribute::getArguments()` returned: positional values keyed by integer, named values keyed by string name.

This shape has three properties worth keeping in mind:

- **Trivially serializable.** `atlas:export` is a one-line `json_encode` because there are no objects to coerce.
- **Resilient.** A renamed or removed attribute class will not break the Atlas; only consumers that explicitly look for that FQCN are affected.
- **Composable.** Multiple bundles can read the same `RouteEntry` and each pull out the attributes they care about, without any of them needing to instantiate the others' classes.

To turn a stored entry back into a real attribute object, unpack the args:

```php
foreach ($route->attributesOf(\App\Attribute\PublicApi::class) as $hit) {
    $attr = new \App\Attribute\PublicApi(...$hit['args']);
}
```

PHP's named-argument unpacking handles mixed positional/named args correctly, so this works whether the attribute was written as `#[PublicApi(2)]` or `#[PublicApi(version: 2)]`.

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

## Which attributes get recorded

Atlas is opinionated. Only attributes whose class FQCN starts with one of these prefixes are captured:

- `Survos\` — every survos attribute (RouteMeta, EntityMeta, RouteIdentity, Field, MeiliIndex, Facet, …)
- `Symfony\Component\Routing\Attribute\` — `#[Route]`
- `Symfony\Component\Security\Http\Attribute\` — `#[IsGranted]`
- `Symfony\Bridge\Twig\Attribute\` — `#[Template]`
- `App\Attribute\` — your own attributes

Anything else — most importantly **ApiPlatform's `#[ApiResource]` and friends** — is silently filtered out. ApiPlatform's attribute universe is huge and deeply nested; analyzing it belongs in `survos/inspection-bundle`, not here. Atlas's scope is "metadata that drives survos features," not "every attribute on every method."

If you genuinely need wider coverage, pass extra prefixes to `AttributeFilter::accepts($fqcn, $extraNamespaces)` from your own builder call — but reconsider the layering first.

## See also

Atlas is intentionally low-level — it records what's there. If you want **opinionated metadata attributes** with semantic helpers on top, install [`survos/field-bundle`](https://packagist.org/packages/survos/field-bundle):

```bash
composer require survos/field-bundle
```

field-bundle defines:

- `#[EntityMeta]` — class-level metadata for admin UI, dashboards, menu auto-registration (icon, group, label, …)
- `#[RouteMeta]` *(in progress)* — method-level metadata for sitemap inclusion, AI introspection, breadcrumb construction, …

Both are discovered through Atlas, and field-bundle ships richer registries (`EntityMetaRegistry`, `RouteMetaRegistry`) plus an enriched `meta:export` command that joins entities and routes into a single graph. Use Atlas directly when you have your own attribute vocabulary; reach for field-bundle when its vocabulary is what you'd be inventing.
