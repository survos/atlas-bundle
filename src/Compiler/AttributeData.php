<?php

declare(strict_types=1);

namespace Survos\AtlasBundle\Compiler;

/**
 * Recursively converts attribute argument data into a container- and JSON-safe shape.
 *
 * `ReflectionAttribute::getArguments()` returns the ORIGINAL constructor arguments
 * the user wrote in the source code — bypassing any normalization the attribute
 * class might do in its constructor body. So entities like:
 *
 *     #[MeiliIndex(persisted: new Fields(...))]
 *
 * give us a `Fields` object in the args array regardless of what `MeiliIndex::__construct`
 * does to normalize. Symfony's XmlDumper refuses arbitrary objects in container
 * arguments — `_AssertionFailedError("Unable to dump a service container if a parameter
 * is an object or a resource, got 'Survos\MeiliBundle\Metadata\Fields'.")` — so the
 * sanitizer walks the args recursively before atlas stores them.
 *
 * Output shape:
 *   - scalars        → as-is
 *   - arrays         → recursed
 *   - BackedEnum     → as-is (Symfony handles these natively in container args)
 *   - UnitEnum       → as-is (Symfony 6.1+ supports both)
 *   - other objects  → ['__class' => FQCN, '__args' => array<recursed public props>]
 *
 * Survos's own attributes (RouteMeta, EntityMeta, RouteIdentity, Field, etc.) take
 * scalar / enum / array-of-string args — so consumers that instantiate them via
 * `new Foo(...$args)` keep working without any reconstruction. Consumers that
 * inspect third-party attributes with nested objects see the round-trippable shape.
 */
final class AttributeData
{
    public static function serialize(mixed $value): mixed
    {
        if (\is_array($value)) {
            return array_map(self::serialize(...), $value);
        }

        if ($value instanceof \BackedEnum || $value instanceof \UnitEnum) {
            return $value;
        }

        if (\is_object($value)) {
            return [
                '__class' => $value::class,
                '__args'  => array_map(self::serialize(...), get_object_vars($value)),
            ];
        }

        return $value;
    }
}
