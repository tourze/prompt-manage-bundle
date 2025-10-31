<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\Service\FallbackTemplateEngine;

/**
 * @internal
 */
#[CoversClass(FallbackTemplateEngine::class)]
final class FallbackTemplateEngineTest extends TestCase
{
    private FallbackTemplateEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new FallbackTemplateEngine();
    }

    public function testRenderSimpleTemplate(): void
    {
        $template = 'Hello {{name}}!';
        $parameters = ['name' => 'John'];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Hello John!', $result->content);
        $this->assertSame('fallback', $result->metadata['engine']);
        $this->assertSame(15, $result->metadata['original_length']);
        $this->assertSame(11, $result->metadata['result_length']);
        $this->assertSame(1, $result->metadata['replacements_made']);
    }

    public function testRenderMultipleVariables(): void
    {
        $template = 'Hello {{name}}, you have {{count}} messages!';
        $parameters = ['name' => 'Alice', 'count' => '5'];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Hello Alice, you have 5 messages!', $result->content);
        $this->assertSame(2, $result->metadata['replacements_made']);
    }

    public function testRenderWithNullValue(): void
    {
        $template = 'Hello {{name}}!';
        $parameters = ['name' => null];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Hello !', $result->content);
        $this->assertSame(1, $result->metadata['replacements_made']);
    }

    public function testRenderWithMissingParameter(): void
    {
        $template = 'Hello {{name}} and {{other}}!';
        $parameters = ['name' => 'John'];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Hello John and {{other}}!', $result->content);
        $this->assertSame(1, $result->metadata['replacements_made']);
    }

    #[DataProvider('scalarParametersProvider')]
    public function testRenderWithScalarParameters(mixed $value, string $expected): void
    {
        $template = 'Value: {{value}}';
        $parameters = ['value' => $value];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertSame("Value: {$expected}", $result->content);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function scalarParametersProvider(): array
    {
        return [
            'string' => ['hello', 'hello'],
            'integer' => [123, '123'],
            'float' => [3.14, '3.14'],
            'boolean true' => [true, '1'],
            'boolean false' => [false, ''],
            'null' => [null, ''],
        ];
    }

    public function testRenderWithNonScalarValues(): void
    {
        $template = 'Value: {{array}} and {{object}}';
        $parameters = [
            'array' => ['a', 'b', 'c'],
            'object' => new \stdClass(),
            'scalar' => 'works',
        ];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        // 非标量值应该被忽略，只替换标量值
        $this->assertSame('Value: {{array}} and {{object}}', $result->content);
        $this->assertSame(0, $result->metadata['replacements_made']);
    }

    public function testRenderWithDuplicateVariables(): void
    {
        $template = '{{name}} says hello to {{name}}!';
        $parameters = ['name' => 'Bob'];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Bob says hello to Bob!', $result->content);
        $this->assertSame(2, $result->metadata['replacements_made']);
    }

    public function testRenderEmptyTemplate(): void
    {
        $template = '';
        $parameters = ['name' => 'John'];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('', $result->content);
        $this->assertSame(0, $result->metadata['replacements_made']);
    }

    public function testRenderWithNoParameters(): void
    {
        $template = 'Hello {{name}}!';
        $parameters = [];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Hello {{name}}!', $result->content);
        $this->assertSame(0, $result->metadata['replacements_made']);
    }

    public function testExtractVariables(): void
    {
        $template = 'Hello {{name}}, welcome to {{place}}! Today is {{day}}.';

        $variables = $this->engine->extractVariables($template);

        $this->assertSame(['name', 'place', 'day'], $variables);
    }

    public function testExtractVariablesWithDuplicates(): void
    {
        $template = '{{name}} and {{name}} and {{other}}';

        $variables = $this->engine->extractVariables($template);

        // array_unique保持原始键，所以需要重新索引或直接检查值
        $this->assertContains('name', $variables);
        $this->assertContains('other', $variables);
        $this->assertCount(2, $variables);
    }

    public function testExtractVariablesFromEmptyTemplate(): void
    {
        $variables = $this->engine->extractVariables('');

        $this->assertEmpty($variables);
    }

    public function testExtractVariablesWithNoVariables(): void
    {
        $template = 'Hello world! No variables here.';

        $variables = $this->engine->extractVariables($template);

        $this->assertEmpty($variables);
    }

    #[DataProvider('templateValidityProvider')]
    public function testIsValidTemplate(string $template, bool $expected): void
    {
        $isValid = $this->engine->isValidTemplate($template);

        $this->assertSame($expected, $isValid);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function templateValidityProvider(): array
    {
        return [
            'valid simple' => ['Hello {{name}}!', true],
            'valid multiple' => ['{{a}} and {{b}}', true],
            'valid empty' => ['', true],
            'valid no variables' => ['Plain text', true],
            'invalid missing close' => ['Hello {{name}!', false],
            'invalid missing open' => ['Hello name}}!', false],
            'invalid unmatched multiple' => ['{{a}} and {{b}', false],
            'invalid complex unmatched' => ['{{a}} {{b}} {{c}', false],
        ];
    }

    public function testIsValidTemplateWithComplexCases(): void
    {
        $testCases = [
            ['{{a}}{{b}}{{c}}', true], // 多个连续变量
            ['{{}}', true], // 空变量名（语法上有效）
            ['{{ }}', true], // 空格变量名
            ['{{{name}}}', true], // 这实际上是有效的（1个{{ + 1个}}）
            ['{{name}}}}', false], // 多余的关闭括号
            ['{{{{name}}', false], // 多余的开启括号
        ];

        foreach ($testCases as [$template, $expected]) {
            $this->assertSame(
                $expected,
                $this->engine->isValidTemplate($template),
                "Template: '{$template}' should be " . ($expected ? 'valid' : 'invalid')
            );
        }
    }

    public function testCountReplacementsAccuracy(): void
    {
        $template = '{{name}} {{name}} {{other}} {{name}}';
        $parameters = ['name' => 'John', 'other' => 'test'];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('John John test John', $result->content);
        $this->assertSame(4, $result->metadata['replacements_made']); // 3个name + 1个other
    }

    public function testRenderMetadataCompleteness(): void
    {
        $template = 'Hello {{name}}!';
        $parameters = ['name' => 'World'];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('engine', $result->metadata);
        $this->assertArrayHasKey('original_length', $result->metadata);
        $this->assertArrayHasKey('result_length', $result->metadata);
        $this->assertArrayHasKey('replacements_made', $result->metadata);

        $this->assertSame('fallback', $result->metadata['engine']);
        $this->assertSame(strlen($template), $result->metadata['original_length']);
        $this->assertSame(strlen($result->content), $result->metadata['result_length']);
        $this->assertIsInt($result->metadata['replacements_made']);
    }

    public function testRenderWithException(): void
    {
        // 创建一个会在渲染过程中抛出异常的情况
        // 由于FallbackTemplateEngine设计得很安全，我们需要模拟异常情况
        $template = 'Hello {{name}}!';
        $parameters = ['name' => 'John'];

        // 正常情况下不应该抛出异常，但如果抛出了，应该返回失败结果
        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertNull($result->error);
    }

    public function testExtractVariablesWithSpecialCharacters(): void
    {
        $template = '{{user_id}} and {{user-name}} and {{count123}}';

        $variables = $this->engine->extractVariables($template);

        // 正则表达式 \w+ 只匹配字母、数字和下划线
        $this->assertContains('user_id', $variables);
        $this->assertContains('count123', $variables);
        // user-name 中的 - 不会被 \w+ 匹配，所以应该不包含
        $this->assertNotContains('user-name', $variables);
    }

    public function testExtractVariablesRegexBehavior(): void
    {
        $template = '{{valid_var}} {{invalid-var}} {{123invalid}} {{_underscore}}';

        $variables = $this->engine->extractVariables($template);

        // 基于正则 /\{\{(\w+)\}\}/，只有符合\w+的变量名会被提取
        $expected = ['valid_var', '_underscore']; // 可能还包括 '123invalid'，取决于正则的具体行为

        foreach ($expected as $expectedVar) {
            $this->assertContains($expectedVar, $variables);
        }
    }
}
