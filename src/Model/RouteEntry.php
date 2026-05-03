<?php

declare(strict_types=1);

namespace Survos\AtlasBundle\Model;

/**
 * One controller-action route discovered at compile time.
 *
 * Attribute payloads are stored as ['class' => FQCN, 'args' => array] rather
 * than instantiated objects so the snapshot is cheap to serialize and survives
 * even if the attribute class fails to load later.
 */
final class RouteEntry
{
    /**
     * @param array<string>                                          $methods         HTTP methods, e.g. ['GET']
     * @param list<array{class: class-string, args: array<mixed>}>   $methodAttributes
     * @param list<array{class: class-string, args: array<mixed>}>   $classAttributes
     */
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly array  $methods,
        public readonly string $controllerClass,
        public readonly string $methodName,
        public readonly array  $methodAttributes,
        public readonly array  $classAttributes,
    ) {}

    public function controller(): string
    {
        return $this->controllerClass . '::' . $this->methodName;
    }

    /**
     * @return list<array{class: class-string, args: array<mixed>}>
     */
    public function attributesOf(string $fqcn): array
    {
        return array_values(array_filter(
            $this->methodAttributes,
            static fn (array $a) => $a['class'] === $fqcn,
        ));
    }

    public function hasAttribute(string $fqcn): bool
    {
        foreach ($this->methodAttributes as $a) {
            if ($a['class'] === $fqcn) {
                return true;
            }
        }
        foreach ($this->classAttributes as $a) {
            if ($a['class'] === $fqcn) {
                return true;
            }
        }
        return false;
    }
}
