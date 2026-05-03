<?php

declare(strict_types=1);

namespace Survos\AtlasBundle\Model;

/**
 * One entity class discovered at compile time.
 *
 * Class-level attributes are stored as ['class' => FQCN, 'args' => array]
 * — see RouteEntry for the rationale.
 */
final class EntityEntry
{
    /**
     * @param list<array{class: class-string, args: array<mixed>}> $classAttributes
     */
    public function __construct(
        public readonly string $fqcn,
        public readonly string $shortName,
        public readonly array  $classAttributes,
    ) {}

    /**
     * @return list<array{class: class-string, args: array<mixed>}>
     */
    public function attributesOf(string $fqcn): array
    {
        return array_values(array_filter(
            $this->classAttributes,
            static fn (array $a) => $a['class'] === $fqcn,
        ));
    }

    public function hasAttribute(string $fqcn): bool
    {
        foreach ($this->classAttributes as $a) {
            if ($a['class'] === $fqcn) {
                return true;
            }
        }
        return false;
    }
}
