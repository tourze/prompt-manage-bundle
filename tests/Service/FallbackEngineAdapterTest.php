<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\DTO\ParseResult;
use Tourze\PromptManageBundle\DTO\RenderResult;
use Tourze\PromptManageBundle\DTO\ValidationResult;
use Tourze\PromptManageBundle\Service\FallbackEngineAdapter;
use Tourze\PromptManageBundle\Service\FallbackTemplateEngine;

/**
 * @internal
 */
#[CoversClass(FallbackEngineAdapter::class)]
final class FallbackEngineAdapterTest extends TestCase
{
    private FallbackTemplateEngine $fallbackEngine;

    private FallbackEngineAdapter $adapter;

    protected function setUp(): void
    {
        $this->fallbackEngine = new FallbackTemplateEngine();
        $this->adapter = new FallbackEngineAdapter($this->fallbackEngine);
    }

    public function testGetName(): void
    {
        $this->assertSame('fallback', $this->adapter->getName());
    }

    public function testGetVersion(): void
    {
        $this->assertSame('1.0.0', $this->adapter->getVersion());
    }

    public function testParseTemplateSuccess(): void
    {
        $template = 'Hello {{name}}, welcome to {{place}}!';

        $result = $this->adapter->parseTemplate($template);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['name', 'place'], $result->getParameterNames());
        $this->assertEquals([
            'name' => ['type' => 'string', 'required' => true],
            'place' => ['type' => 'string', 'required' => true],
        ], $result->parameters);
        $this->assertEmpty($result->warnings);
        $this->assertNull($result->error);
    }

    public function testParseTemplateWithNoVariables(): void
    {
        $template = 'Hello world!';

        $result = $this->adapter->parseTemplate($template);

        $this->assertTrue($result->isSuccess());
        $this->assertEmpty($result->getParameterNames());
        $this->assertEmpty($result->parameters);
    }

    public function testRender(): void
    {
        $template = 'Hello {{name}}!';
        $parameters = ['name' => 'John'];

        $result = $this->adapter->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Hello John!', $result->content);
        $this->assertSame('fallback', $result->metadata['engine']);
    }

    public function testValidateTemplateValid(): void
    {
        $template = 'Hello {{name}}!';

        $result = $this->adapter->validateTemplate($template);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors);
        $this->assertEquals(['engine' => 'fallback'], $result->metadata);
    }

    public function testValidateTemplateInvalid(): void
    {
        $template = 'Hello {{name}!'; // 缺少闭合括号

        $result = $this->adapter->validateTemplate($template);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasErrors());
        $this->assertContains('Template syntax validation failed', $result->errors);
        $this->assertEquals(['engine' => 'fallback'], $result->metadata);
    }

    public function testIsAvailable(): void
    {
        $this->assertTrue($this->adapter->isAvailable());
    }

    public function testGetSupportedFeatures(): void
    {
        $features = $this->adapter->getSupportedFeatures();

        $this->assertIsArray($features);
        $this->assertContains('simple_variable_substitution', $features);
    }

    public function testGetConfiguration(): void
    {
        $config = $this->adapter->getConfiguration();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('engine', $config);
        $this->assertArrayHasKey('safe_mode', $config);
        $this->assertArrayHasKey('features', $config);
        $this->assertArrayHasKey('max_complexity', $config);

        $this->assertSame('fallback', $config['engine']);
        $this->assertTrue($config['safe_mode']);
        $this->assertIsArray($config['features']);
        $this->assertContains('variable_substitution', $config['features']);
        $this->assertSame('low', $config['max_complexity']);
    }

    /**
     * @param array<string> $expectedVariables
     */
    #[DataProvider('parseTemplateVariablesProvider')]
    public function testParseTemplateWithDifferentVariables(string $template, array $expectedVariables): void
    {
        $result = $this->adapter->parseTemplate($template);

        $this->assertTrue($result->isSuccess());
        $this->assertSame($expectedVariables, $result->getParameterNames());

        foreach ($expectedVariables as $variable) {
            $this->assertArrayHasKey($variable, $result->parameters);
            $this->assertEquals(
                ['type' => 'string', 'required' => true],
                $result->parameters[$variable]
            );
        }
    }

    /**
     * @return array<string, array{string, array<string>}>
     */
    public static function parseTemplateVariablesProvider(): array
    {
        return [
            'no variables' => ['Plain text', []],
            'single variable' => ['Hello {{name}}!', ['name']],
            'multiple variables' => ['{{greeting}} {{name}}, welcome to {{place}}!', ['greeting', 'name', 'place']],
            'duplicate variables' => ['{{name}} and {{name}} again', ['name']], // extractVariables应该去重
            'mixed content' => ['User {{user_id}} has {{count}} items', ['user_id', 'count']],
        ];
    }

    public function testValidateTemplateEdgeCases(): void
    {
        $testCases = [
            ['', true], // 空模板
            ['{{}}', true], // 空变量名（语法上有效）
            ['{{invalid template', false], // 语法错误
        ];

        foreach ($testCases as [$template, $isValid]) {
            $result = $this->adapter->validateTemplate($template);
            $this->assertSame($isValid, $result->isValid(), "Template: '{$template}' validation failed");
        }
    }

    public function testFullWorkflow(): void
    {
        $template = 'Hello {{name}}, you have {{count}} messages!';

        // 测试解析
        $parseResult = $this->adapter->parseTemplate($template);
        $this->assertTrue($parseResult->isSuccess());
        $this->assertSame(['name', 'count'], $parseResult->getParameterNames());

        // 测试验证
        $validationResult = $this->adapter->validateTemplate($template);
        $this->assertTrue($validationResult->isValid());

        // 测试渲染
        $renderResult = $this->adapter->render($template, ['name' => 'John', 'count' => '5']);
        $this->assertTrue($renderResult->isSuccess());
        $this->assertSame('Hello John, you have 5 messages!', $renderResult->content);
    }
}
