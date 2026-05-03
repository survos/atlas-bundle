<?php

declare(strict_types=1);

namespace Survos\AtlasBundle\Compiler;

/**
 * Decides which attribute classes the atlas records.
 *
 * Atlas is intentionally narrow: it captures survos's metadata vocabulary
 * (RouteMeta, EntityMeta, RouteIdentity, Field, MeiliIndex, Facet, …) plus
 * the few Symfony attributes consumers care about (#[Route], #[IsGranted],
 * #[Template]). It does NOT capture ApiPlatform's elaborate attribute
 * universe — those live in their own analysis bundle (survos/inspection-bundle
 * today, eventually api-inspection-bundle).
 *
 * This filter exists because:
 *
 *   1. It keeps the dumped container small and serializable. ApiPlatform's
 *      `#[ApiResource(operations: [new Get(...), ...])]` puts nested objects
 *      into attribute args; XmlDumper rejects them.
 *
 *   2. It gives atlas a sharp, opinionated scope: "metadata that drives
 *      survos features" — not "every attribute on every method."
 *
 *   3. Every recorded attribute can be reconstructed via `new Foo(...$args)`,
 *      since survos attributes use scalar / enum / array-of-string args.
 *      No sanitization needed.
 *
 * If you genuinely need wider attribute coverage in your app, pass extra
 * namespaces in via the second arg of `accepts()` or extend
 * `survos_atlas.attribute_namespaces` (parameter wiring is a future addition
 * — for now the default list covers every survos workspace need).
 */
final class AttributeFilter
{
    public const array DEFAULT_NAMESPACES = [
        'Survos\\',                                                // every survos attribute
        'Symfony\\Component\\Routing\\Attribute\\',                // Route
        'Symfony\\Component\\Security\\Http\\Attribute\\',         // IsGranted
        'Symfony\\Bridge\\Twig\\Attribute\\',                      // Template
        'App\\Attribute\\',                                        // user-defined attrs
    ];

    /**
     * @param list<string> $extraNamespaces Additional namespace prefixes to accept.
     */
    public static function accepts(string $fqcn, array $extraNamespaces = []): bool
    {
        foreach (self::DEFAULT_NAMESPACES as $prefix) {
            if (str_starts_with($fqcn, $prefix)) {
                return true;
            }
        }
        foreach ($extraNamespaces as $prefix) {
            if (str_starts_with($fqcn, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
