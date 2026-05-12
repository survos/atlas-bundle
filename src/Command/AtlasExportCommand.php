<?php

declare(strict_types=1);

namespace Survos\AtlasBundle\Command;

use Survos\AtlasBundle\Model\EntityEntry;
use Survos\AtlasBundle\Model\RouteEntry;
use Survos\AtlasBundle\Service\Atlas;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand('atlas:export', 'Dump the discovered atlas (routes + entities + their attributes) as JSON or YAML.')]
final class AtlasExportCommand
{
    public function __construct(
        private readonly Atlas $atlas,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('json|yaml')] string $format = 'json',
        #[Option('Write to file instead of stdout')] ?string $output = null,
        #[Option('Pretty-print JSON')] bool $pretty = false,
    ): int {
        $format = strtolower($format);

        $payload = [
            'routes'   => array_map(self::serializeRoute(...), $this->atlas->routes()),
            'entities' => array_map(self::serializeEntity(...), $this->atlas->entities()),
        ];

        $serialized = match ($format) {
            'json' => json_encode($payload, ($pretty ? \JSON_PRETTY_PRINT : 0) | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            'yaml', 'yml' => Yaml::dump($payload, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
            default => null,
        };

        if ($serialized === null || $serialized === false) {
            $io->error(sprintf('Unsupported format "%s" (expected json or yaml).', $format));
            return Command::INVALID;
        }

        if ($output !== null && $output !== '') {
            file_put_contents($output, $serialized);
            $io->success(sprintf('Wrote %d routes and %d entities to %s', \count($payload['routes']), \count($payload['entities']), $output));
            return Command::SUCCESS;
        }

        $io->writeln($serialized);
        return Command::SUCCESS;
    }

    /** @return array<string, mixed> */
    private static function serializeRoute(RouteEntry $r): array
    {
        return [
            'name'             => $r->name,
            'path'             => $r->path,
            'methods'          => $r->methods,
            'controller'       => $r->controller(),
            'methodAttributes' => $r->methodAttributes,
            'classAttributes'  => $r->classAttributes,
        ];
    }

    /** @return array<string, mixed> */
    private static function serializeEntity(EntityEntry $e): array
    {
        return [
            'fqcn'            => $e->fqcn,
            'shortName'       => $e->shortName,
            'classAttributes' => $e->classAttributes,
        ];
    }
}
