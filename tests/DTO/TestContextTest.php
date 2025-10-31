<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\DTO\TestContext;

/**
 * @internal
 */
#[CoversClass(TestContext::class)]
final class TestContextTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $template = 'Hello {{name}}!';
        $parameters = ['name' => 'World'];

        $context = new TestContext($template, $parameters);

        $this->assertSame($template, $context->template);
        $this->assertSame($parameters, $context->parameters);
        $this->assertSame(5000, $context->timeoutMs);
        $this->assertSame('twig', $context->engine);
    }

    public function testConstructorWithCustomValues(): void
    {
        $template = 'Custom template {{value}}';
        $parameters = ['value' => 'test', 'count' => 42];
        $timeoutMs = 3000;
        $engine = 'custom';

        $context = new TestContext($template, $parameters, $timeoutMs, $engine);

        $this->assertSame($template, $context->template);
        $this->assertSame($parameters, $context->parameters);
        $this->assertSame($timeoutMs, $context->timeoutMs);
        $this->assertSame($engine, $context->engine);
    }

    public function testWithEmptyParameters(): void
    {
        $template = 'Static template without parameters';
        $parameters = [];

        $context = new TestContext($template, $parameters);

        $this->assertSame($template, $context->template);
        $this->assertSame([], $context->parameters);
        $this->assertSame(5000, $context->timeoutMs);
        $this->assertSame('twig', $context->engine);
    }

    public function testWithComplexParameters(): void
    {
        $template = 'Complex template';
        $parameters = [
            'string' => 'value',
            'integer' => 123,
            'array' => ['a', 'b', 'c'],
            'nested' => ['key' => 'value', 'number' => 456],
        ];

        $context = new TestContext($template, $parameters);

        $this->assertSame($template, $context->template);
        $this->assertSame($parameters, $context->parameters);
        $this->assertIsArray($context->parameters['array']);
        $this->assertIsArray($context->parameters['nested']);
        $this->assertSame('value', $context->parameters['string']);
        $this->assertSame(123, $context->parameters['integer']);
    }

    public function testReadonlyProperties(): void
    {
        $context = new TestContext('template', ['param' => 'value']);

        // 验证属性是只读的 - 这个测试主要是为了确保设计意图
        $this->assertTrue(true); // 如果代码能运行到这里，说明只读属性工作正常

        // 可以通过反射验证只读性质
        $reflection = new \ReflectionClass($context);
        $templateProperty = $reflection->getProperty('template');
        $this->assertTrue($templateProperty->isReadOnly());

        $parametersProperty = $reflection->getProperty('parameters');
        $this->assertTrue($parametersProperty->isReadOnly());
    }

    public function testMinimalTimeout(): void
    {
        $context = new TestContext('template', [], 1);

        $this->assertSame(1, $context->timeoutMs);
    }

    public function testLargeTimeout(): void
    {
        $context = new TestContext('template', [], 60000);

        $this->assertSame(60000, $context->timeoutMs);
    }

    public function testDifferentEngines(): void
    {
        $engines = ['twig', 'smarty', 'blade', 'mustache', 'custom'];

        foreach ($engines as $engine) {
            $context = new TestContext('template', [], 5000, $engine);
            $this->assertSame($engine, $context->engine);
        }
    }
}
