<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\RouteCollection;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\PromptManageBundle\Service\AttributeControllerLoader;

/**
 * @internal
 */
#[CoversClass(AttributeControllerLoader::class)]
#[RunTestsInSeparateProcesses]
final class AttributeControllerLoaderTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 无需特殊设置
    }

    private function getLoader(): AttributeControllerLoader
    {
        $loaderService = self::getContainer()->get(AttributeControllerLoader::class);
        if (!$loaderService instanceof AttributeControllerLoader) {
            self::fail('AttributeControllerLoader service is not of expected type');
        }

        return $loaderService;
    }

    public function testConstructorInitializesRouteCollection(): void
    {
        $loader = $this->getLoader();
        $collection = $loader->load('dummy');

        $this->assertInstanceOf(RouteCollection::class, $collection);
        $this->assertGreaterThan(0, $collection->count());
    }

    public function testLoadReturnsRouteCollection(): void
    {
        $loader = $this->getLoader();
        $collection = $loader->load('dummy');

        $this->assertInstanceOf(RouteCollection::class, $collection);
    }

    public function testLoadReturnsConsistentResults(): void
    {
        $loader = $this->getLoader();
        $collection1 = $loader->load('dummy');
        $collection2 = $loader->load('anything');

        // 应该返回相同的路由集合，因为load方法不依赖参数
        $this->assertEquals($collection1->count(), $collection2->count());

        $routes1 = array_keys($collection1->all());
        $routes2 = array_keys($collection2->all());

        $this->assertEquals($routes1, $routes2);
    }

    public function testLoadWithNullTypeReturnsRouteCollection(): void
    {
        $loader = $this->getLoader();
        $collection = $loader->load('resource', null);

        $this->assertInstanceOf(RouteCollection::class, $collection);
    }

    public function testLoadWithSpecificTypeReturnsRouteCollection(): void
    {
        $loader = $this->getLoader();
        $collection = $loader->load('resource', 'annotation');

        $this->assertInstanceOf(RouteCollection::class, $collection);
    }

    public function testSupportsAlwaysReturnsFalse(): void
    {
        $loader = $this->getLoader();
        $this->assertFalse($loader->supports('resource'));
        $this->assertFalse($loader->supports('resource', 'annotation'));
        $this->assertFalse($loader->supports('resource', null));
        $this->assertFalse($loader->supports('anything'));
    }

    public function testSupportsWithDifferentResourceTypes(): void
    {
        $loader = $this->getLoader();
        $testCases = [
            ['file.yaml', 'yaml'],
            ['config.xml', 'xml'],
            ['routes.php', 'php'],
            [123, 'number'],
            [[], 'array'],
            [new \stdClass(), 'object'],
        ];

        foreach ($testCases as [$resource, $description]) {
            $this->assertFalse(
                $loader->supports($resource),
                "supports() should return false for {$description} resource"
            );
        }
    }

    public function testAutoloadReturnsRouteCollection(): void
    {
        $loader = $this->getLoader();
        $collection = $loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $collection);
    }

    public function testAutoloadReturnsSameCollectionAsLoad(): void
    {
        $loader = $this->getLoader();
        $loadCollection = $loader->load('dummy');
        $autoloadCollection = $loader->autoload();

        $this->assertEquals($loadCollection->count(), $autoloadCollection->count());

        $loadRoutes = array_keys($loadCollection->all());
        $autoloadRoutes = array_keys($autoloadCollection->all());

        $this->assertEquals($loadRoutes, $autoloadRoutes);
    }

    public function testRouteCollectionContainsExpectedControllerRoutes(): void
    {
        $loader = $this->getLoader();
        $collection = $loader->load('dummy');
        $routeNames = array_keys($collection->all());

        // 验证包含了TestingController和TestingParametersController的路由
        $hasTestingRoutes = false;
        $hasParametersRoutes = false;

        foreach ($routeNames as $routeName) {
            if (str_contains($routeName, 'testing')) {
                $hasTestingRoutes = true;
            }
            if (str_contains($routeName, 'parameters') || str_contains($routeName, 'testing')) {
                $hasParametersRoutes = true;
            }
        }

        $this->assertTrue(
            $hasTestingRoutes || $hasParametersRoutes,
            'Route collection should contain routes from TestingController and/or TestingParametersController'
        );
    }

    public function testRouteCollectionIsNotEmpty(): void
    {
        $loader = $this->getLoader();
        $collection = $loader->load('dummy');

        $this->assertGreaterThan(0, $collection->count());
        $this->assertNotEmpty($collection->all());
    }

    public function testMultipleCallsReturnSameInstance(): void
    {
        $loader = $this->getLoader();
        $collection1 = $loader->load('resource1');
        $collection2 = $loader->load('resource2');
        $collection3 = $loader->autoload();

        // 所有调用应该返回相同的路由集合实例
        $this->assertSame($collection1, $collection2);
        $this->assertSame($collection2, $collection3);
    }
}
