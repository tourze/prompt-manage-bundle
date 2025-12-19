<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\DTO\ParseResult;
use Tourze\PromptManageBundle\DTO\RenderResult;
use Tourze\PromptManageBundle\DTO\ValidationResult;
use Tourze\PromptManageBundle\Service\FallbackTemplateEngine;
use Tourze\PromptManageBundle\Service\TemplateEngineInterface;
use Tourze\PromptManageBundle\Service\TemplateEngineRegistry;

/**
 * @internal
 */
#[CoversClass(TemplateEngineRegistry::class)]
final class TemplateEngineRegistryTest extends TestCase
{
    private TemplateEngineRegistry $registry;

    protected function setUp(): void
    {
        $fallbackEngine = new FallbackTemplateEngine();
        $this->registry = new TemplateEngineRegistry($fallbackEngine);
    }

    public function testEnginesCanBeRegisteredAndRetrieved(): void
    {
        $mockEngine = $this->createMockEngine('test');

        $this->registry->register($mockEngine);

        $this->assertTrue($this->registry->hasEngine('test'));
        $this->assertSame($mockEngine, $this->registry->getEngine('test'));

        $availableEngines = $this->registry->getAvailableEngineNames();
        $this->assertContains('test', $availableEngines);
    }

    public function testNonExistentEngineThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Template engine \'nonexistent\' not found');

        $this->registry->getEngine('nonexistent');
    }

    public function testBestAvailableEngineIsReturned(): void
    {
        $primaryEngine = $this->createMockEngine('primary');
        $fallbackEngine = $this->createMockEngine('fallback');

        $this->registry->register($primaryEngine);
        $this->registry->register($fallbackEngine);
        $this->registry->setPriority('primary', 10);
        $this->registry->setPriority('fallback', 5);

        $bestEngine = $this->registry->getBestAvailableEngine();

        $this->assertSame($primaryEngine, $bestEngine);
    }

    public function testEnginePriorityOrderingWorks(): void
    {
        $lowPriorityEngine = $this->createMockEngine('low');
        $highPriorityEngine = $this->createMockEngine('high');
        $mediumPriorityEngine = $this->createMockEngine('medium');

        $this->registry->register($lowPriorityEngine);
        $this->registry->register($highPriorityEngine);
        $this->registry->register($mediumPriorityEngine);

        $this->registry->setPriority('low', 1);
        $this->registry->setPriority('high', 10);
        $this->registry->setPriority('medium', 5);

        $engines = $this->registry->getAllEngines();
        $engineNames = array_keys($engines);

        $this->assertSame(['high', 'medium', 'low'], $engineNames);
    }

    public function testEnginesCanBeReplaced(): void
    {
        $originalEngine = $this->createMockEngine('test');
        $newEngine = $this->createMockEngine('test'); // 同名引擎会替换

        $this->registry->register($originalEngine);
        $this->assertSame($originalEngine, $this->registry->getEngine('test'));

        $this->registry->register($newEngine);
        $this->assertSame($newEngine, $this->registry->getEngine('test'));
    }

    public function testEnginesCanBeRemoved(): void
    {
        $engine = $this->createMockEngine('removable');

        $this->registry->register($engine);
        $this->assertTrue($this->registry->hasEngine('removable'));

        $this->registry->remove('removable');
        $this->assertFalse($this->registry->hasEngine('removable'));
    }

    public function testDefaultEngineConfiguration(): void
    {
        $engine = $this->createMockEngine('default');

        $this->registry->register($engine, true); // 设为默认引擎

        $defaultEngine = $this->registry->getDefaultEngine();
        $this->assertSame($engine, $defaultEngine);
    }

    public function testAllEngineNamesCanBeRetrieved(): void
    {
        $this->registry->register($this->createMockEngine('engine1'));
        $this->registry->register($this->createMockEngine('engine2'));
        $this->registry->register($this->createMockEngine('engine3'));

        $names = $this->registry->getEngineNames();

        $this->assertContains('engine1', $names);
        $this->assertContains('engine2', $names);
        $this->assertContains('engine3', $names);
        $this->assertCount(3, $names);
    }

    public function testEngineHealthCheck(): void
    {
        $healthyEngine = $this->createMockEngine('healthy', true);
        $unhealthyEngine = $this->createMockEngine('unhealthy', false);

        $this->registry->register($healthyEngine);
        $this->registry->register($unhealthyEngine);

        $healthyEngines = $this->registry->getHealthyEngines();

        $this->assertArrayHasKey('healthy', $healthyEngines);
        $this->assertArrayNotHasKey('unhealthy', $healthyEngines);
    }

    public function testEngineStatisticsAreTracked(): void
    {
        $engine1 = $this->createMockEngine('engine1');
        $engine2 = $this->createMockEngine('engine2');

        $this->registry->register($engine1);
        $this->registry->register($engine2);

        $stats = $this->registry->getStatistics();

        $this->assertArrayHasKey('total_engines', $stats);
        $this->assertArrayHasKey('healthy_engines', $stats);
        $this->assertSame(2, $stats['total_engines']);
        $this->assertSame(2, $stats['healthy_engines']);
    }

    public function testEnginesCanBeBatchRegistered(): void
    {
        $engines = [
            $this->createMockEngine('twig'),
            $this->createMockEngine('mustache'),
            $this->createMockEngine('smarty'),
        ];

        $priorities = [
            'twig' => 10,
            'mustache' => 5,
            'smarty' => 1,
        ];

        $this->registry->registerBatch($engines, $priorities);

        $this->assertTrue($this->registry->hasEngine('twig'));
        $this->assertTrue($this->registry->hasEngine('mustache'));
        $this->assertTrue($this->registry->hasEngine('smarty'));

        $bestEngine = $this->registry->getBestAvailableEngine();
        $this->assertEquals('twig', $bestEngine->getName());
    }

    public function testEngineFallbackBehavior(): void
    {
        $primaryEngine = $this->createMockEngine('primary', false); // 不可用
        $fallbackEngine = $this->createMockEngine('fallback', true); // 可用

        $this->registry->register($primaryEngine);
        $this->registry->register($fallbackEngine);
        $this->registry->setPriority('primary', 10);
        $this->registry->setPriority('fallback', 5);

        $bestHealthyEngine = $this->registry->getBestHealthyEngine();

        $this->assertSame($fallbackEngine, $bestHealthyEngine);
    }

    public function testGetStatus(): void
    {
        $engine1 = $this->createMockEngine('engine1');
        $engine2 = $this->createMockEngine('engine2', false);

        $this->registry->register($engine1, true);
        $this->registry->register($engine2);

        $status = $this->registry->getStatus();

        $this->assertEquals('engine1', $status['default_engine']);
        $this->assertEquals(2, $status['total_engines']);
        $this->assertEquals(1, $status['available_engines']);
        $this->assertArrayHasKey('engines', $status);
    }

    public function testRemoveNonExistentEngine(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Template engine \'nonexistent\' not found');

        $this->registry->remove('nonexistent');
    }

    public function testNoDefaultEngineThrowsException(): void
    {
        // 创建一个没有注册任何引擎的新注册表
        $fallbackEngine = new FallbackTemplateEngine();
        $emptyRegistry = new TemplateEngineRegistry($fallbackEngine);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No default template engine configured');

        $emptyRegistry->getDefaultEngine();
    }

    public function testSetPriorityForNonExistentEngine(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Template engine \'nonexistent\' not found');

        $this->registry->setPriority('nonexistent', 10);
    }

    public function testRemoveDefaultEngineReassignsDefault(): void
    {
        $engine1 = $this->createMockEngine('engine1');
        $engine2 = $this->createMockEngine('engine2');

        $this->registry->register($engine1, true); // 设为默认
        $this->registry->register($engine2);

        $this->assertEquals('engine1', $this->registry->getDefaultEngine()->getName());

        $this->registry->remove('engine1');

        $this->assertEquals('engine2', $this->registry->getDefaultEngine()->getName());
    }

    public function testBestAvailableEngineWithFallback(): void
    {
        // 当没有可用引擎时，应该返回 FallbackEngineAdapter
        $unavailableEngine = $this->createMockEngine('unavailable', false);
        $this->registry->register($unavailableEngine);

        $bestEngine = $this->registry->getBestAvailableEngine();

        // 应该返回 FallbackEngineAdapter
        $this->assertEquals('fallback', $bestEngine->getName());
    }

    /**
     * 测试 register 方法 - 标准命名约定
     */
    public function testRegister(): void
    {
        $mockEngine = $this->createMockEngine('test');

        $this->registry->register($mockEngine);

        $this->assertTrue($this->registry->hasEngine('test'));
        $this->assertSame($mockEngine, $this->registry->getEngine('test'));
    }

    /**
     * 测试 registerBatch 方法 - 标准命名约定
     */
    public function testRegisterBatch(): void
    {
        $engines = [
            $this->createMockEngine('engine1'),
            $this->createMockEngine('engine2'),
        ];

        $this->registry->registerBatch($engines);

        $this->assertTrue($this->registry->hasEngine('engine1'));
        $this->assertTrue($this->registry->hasEngine('engine2'));
    }

    /**
     * 创建测试引擎实例
     */
    private function createMockEngine(string $name, bool $isAvailable = true): TemplateEngineInterface
    {
        return new class($name, $isAvailable) implements TemplateEngineInterface {
            public function __construct(
                private readonly string $name,
                private readonly bool $isAvailable = true,
            ) {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getVersion(): string
            {
                return '1.0.0';
            }

            public function parseTemplate(string $template): ParseResult
            {
                return new ParseResult(true, [], []);
            }

            public function render(string $template, array $parameters): RenderResult
            {
                return new RenderResult(true, "rendered_{$this->name}", []);
            }

            public function validateTemplate(string $template): ValidationResult
            {
                return new ValidationResult(true, [], []);
            }

            public function isAvailable(): bool
            {
                return $this->isAvailable;
            }

            public function getSupportedFeatures(): array
            {
                return ['basic'];
            }

            public function getConfiguration(): array
            {
                return ['engine' => $this->name];
            }
        };
    }
}
