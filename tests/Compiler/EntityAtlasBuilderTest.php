<?php

declare(strict_types=1);

namespace Survos\AtlasBundle\Tests\Compiler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Survos\AtlasBundle\Compiler\EntityAtlasBuilder;
use Survos\AtlasBundle\Tests\Fixtures\ExampleBundle\Entity\ExampleEntity;
use Survos\AtlasBundle\Tests\Fixtures\ExampleBundle\ExampleBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once __DIR__.'/../Fixtures/ExampleBundle/src/ExampleBundle.php';
require_once __DIR__.'/../Fixtures/ExampleBundle/src/Entity/ExampleEntity.php';

final class EntityAtlasBuilderTest extends TestCase
{
    #[DataProvider('bundlePaths')]
    public function testDiscoversAdaptedBundleEntitiesUsingMetadataPath(string $path): void
    {
        $container = $this->createContainer();
        // Symfony 8.2 can expose the adapter class while compiling the container.
        $container->setParameter('kernel.bundles', [
            'ExampleBundle' => 'Symfony\\Component\\HttpKernel\\Bundle\\BundleAdapter',
        ]);
        $container->setParameter('kernel.bundles_metadata', [
            'ExampleBundle' => ['path' => $path],
        ]);

        self::assertSame([ExampleEntity::class], array_column(EntityAtlasBuilder::build($container), 'fqcn'));
    }

    public static function bundlePaths(): iterable
    {
        yield 'package root' => [__DIR__.'/../Fixtures/ExampleBundle'];
        yield 'source directory' => [__DIR__.'/../Fixtures/ExampleBundle/src'];
    }

    public function testFallsBackToBundleClassWhenMetadataIsUnavailable(): void
    {
        $container = $this->createContainer();
        $container->setParameter('kernel.bundles', ['ExampleBundle' => ExampleBundle::class]);

        self::assertSame([ExampleEntity::class], array_column(EntityAtlasBuilder::build($container), 'fqcn'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__.'/../Fixtures');

        return $container;
    }
}
