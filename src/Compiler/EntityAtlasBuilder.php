<?php

declare(strict_types=1);

namespace Survos\AtlasBundle\Compiler;

use Survos\AtlasBundle\Model\EntityEntry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;

/**
 * Discovers entity classes at compile time across:
 *
 *   1. Every directory named in doctrine.orm.mappings (if Doctrine is installed)
 *   2. %kernel.project_dir%/src/Entity
 *   3. Every registered bundle's Entity/ subdir
 *   4. Any extra directories the caller passes in
 *
 * Returns one EntityEntry per discovered class with its class-level attributes
 * serialized as {class, args} pairs.
 */
final class EntityAtlasBuilder
{
    /**
     * @param  list<string> $extraDirs
     * @return list<EntityEntry>
     */
    public static function build(ContainerBuilder $container, array $extraDirs = []): array
    {
        $dirs    = self::resolveDirs($container, $extraDirs);
        $entries = [];
        $seen    = [];

        foreach ($dirs as $dir) {
            foreach (self::fqcnsIn($dir) as $fqcn) {
                if (isset($seen[$fqcn]) || !class_exists($fqcn)) {
                    continue;
                }
                $seen[$fqcn] = true;

                try {
                    $rc = new \ReflectionClass($fqcn);
                } catch (\ReflectionException) {
                    continue;
                }

                if ($rc->isAbstract() || $rc->isInterface() || $rc->isTrait()) {
                    continue;
                }

                $classAttrs = [];
                foreach ($rc->getAttributes() as $a) {
                    if (!AttributeFilter::accepts($a->getName())) {
                        continue;
                    }
                    $classAttrs[] = [
                        'class' => $a->getName(),
                        'args'  => array_map(AttributeData::serialize(...), $a->getArguments()),
                    ];
                }

                $entries[] = new EntityEntry(
                    fqcn:            $fqcn,
                    shortName:       $rc->getShortName(),
                    classAttributes: $classAttrs,
                );
            }
        }

        usort($entries, static fn (EntityEntry $a, EntityEntry $b) => $a->fqcn <=> $b->fqcn);

        return $entries;
    }

    /**
     * @param  list<string> $extraDirs
     * @return list<string>
     */
    private static function resolveDirs(ContainerBuilder $container, array $extraDirs): array
    {
        $dirs = [];

        if ($container->hasParameter('doctrine.orm.mappings')) {
            $mappings = (array) $container->getParameter('doctrine.orm.mappings');
            foreach ($mappings as $cfg) {
                if (\is_array($cfg) && isset($cfg['dir']) && \is_dir($cfg['dir'])) {
                    $dirs[] = $cfg['dir'];
                }
            }
        }

        $projectDir = (string) $container->getParameter('kernel.project_dir');
        $appEntityDir = $projectDir . '/src/Entity';
        if (\is_dir($appEntityDir)) {
            $dirs[] = $appEntityDir;
        }

        foreach ((array) $container->getParameter('kernel.bundles') as $bundleClass) {
            try {
                $bundleDir = \dirname((new \ReflectionClass($bundleClass))->getFileName());
            } catch (\ReflectionException) {
                continue;
            }
            $entityDir = $bundleDir . '/Entity';
            if (\is_dir($entityDir)) {
                $dirs[] = $entityDir;
            }
        }

        foreach ($extraDirs as $dir) {
            if (\is_string($dir) && \is_dir($dir)) {
                $dirs[] = $dir;
            }
        }

        return array_values(array_unique($dirs));
    }

    /**
     * Yield candidate FQCNs from .php files without autoloading them first.
     *
     * @return iterable<string>
     */
    private static function fqcnsIn(string $dir): iterable
    {
        if (!\is_dir($dir)) {
            return;
        }

        foreach ((new Finder())->files()->in($dir)->name('*.php') as $file) {
            $src = @file_get_contents($file->getPathname());
            if ($src === false) {
                continue;
            }

            if (!preg_match('/namespace\s+([^;]+);/m', $src, $ns)) {
                continue;
            }

            if (!preg_match('/\b(?:final\s+|abstract\s+|readonly\s+)*class\s+([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)\b/m', $src, $cls)) {
                continue;
            }

            yield trim($ns[1]) . '\\' . trim($cls[1]);
        }
    }
}
