<?php

declare(strict_types=1);

namespace Survos\AtlasBundle\Compiler;

use Survos\AtlasBundle\Model\RouteEntry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Walks every controller registered with the container.service_subscriber tag
 * (the Symfony default for controllers extending AbstractController) and emits
 * one RouteEntry per #[Route] method.
 *
 * Pure compile-time helper. Instantiate inside your own CompilerPassInterface
 * or call statically from a bundle's build() / process() method.
 */
final class ControllerAtlasBuilder
{
    /**
     * @return list<RouteEntry>
     */
    public static function build(ContainerBuilder $container): array
    {
        $entries = [];
        $tagged  = $container->findTaggedServiceIds('container.service_subscriber');

        foreach (array_keys($tagged) as $controllerClass) {
            if (!\is_string($controllerClass) || !class_exists($controllerClass)) {
                continue;
            }

            try {
                $rc = new \ReflectionClass($controllerClass);
            } catch (\ReflectionException) {
                continue;
            }

            $classAttrs = self::serializeAttributes($rc->getAttributes());

            foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $routeAttrs = $method->getAttributes(Route::class);
                if ($routeAttrs === []) {
                    continue;
                }

                $methodAttrs = self::serializeAttributes($method->getAttributes());

                foreach ($routeAttrs as $routeAttr) {
                    $args = $routeAttr->getArguments();
                    $name = $args['name'] ?? null;
                    if (!\is_string($name) || $name === '') {
                        continue;
                    }

                    $path    = self::stringArg($args, 'path', 0) ?? '';
                    $methods = self::arrayArg($args, 'methods') ?? [];

                    $entries[] = new RouteEntry(
                        name:             $name,
                        path:             $path,
                        methods:          $methods,
                        controllerClass:  $controllerClass,
                        methodName:       $method->getName(),
                        methodAttributes: $methodAttrs,
                        classAttributes:  $classAttrs,
                    );
                }
            }
        }

        usort($entries, static fn (RouteEntry $a, RouteEntry $b) => $a->name <=> $b->name);

        return $entries;
    }

    /**
     * @param  array<\ReflectionAttribute>                              $reflAttrs
     * @return list<array{class: class-string, args: array<mixed>}>
     */
    private static function serializeAttributes(array $reflAttrs): array
    {
        $out = [];
        foreach ($reflAttrs as $a) {
            if (!AttributeFilter::accepts($a->getName())) {
                continue;
            }
            $out[] = [
                'class' => $a->getName(),
                'args'  => array_map(AttributeData::serialize(...), $a->getArguments()),
            ];
        }
        return $out;
    }

    /**
     * @param array<int|string, mixed> $args
     */
    private static function stringArg(array $args, string $name, int $position): ?string
    {
        $v = $args[$name] ?? $args[$position] ?? null;
        return \is_string($v) ? $v : null;
    }

    /**
     * @param  array<int|string, mixed> $args
     * @return list<string>|null
     */
    private static function arrayArg(array $args, string $name): ?array
    {
        $v = $args[$name] ?? null;
        if (\is_string($v)) {
            return [$v];
        }
        if (\is_array($v)) {
            return array_values(array_filter($v, 'is_string'));
        }
        return null;
    }
}
