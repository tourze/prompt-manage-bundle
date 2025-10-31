<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\DTO\ParseResult;

/**
 * @internal
 */
#[CoversClass(ParseResult::class)]
final class ParseResultTest extends TestCase
{
    public function testSuccessfulParseWithParameters(): void
    {
        $parameters = [
            'name' => ['type' => 'string', 'required' => true],
            'age' => ['type' => 'integer', 'required' => false],
            'email' => ['type' => 'string', 'required' => true, 'format' => 'email'],
        ];

        $result = new ParseResult(true, $parameters);

        $this->assertTrue($result->success);
        $this->assertSame($parameters, $result->parameters);
        $this->assertSame([], $result->warnings);
        $this->assertNull($result->error);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->hasWarnings());

        $expectedNames = ['name', 'age', 'email'];
        $this->assertSame($expectedNames, $result->getParameterNames());
    }

    public function testSuccessfulParseWithoutParameters(): void
    {
        $result = new ParseResult(true);

        $this->assertTrue($result->success);
        $this->assertSame([], $result->parameters);
        $this->assertSame([], $result->warnings);
        $this->assertNull($result->error);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->hasWarnings());
        $this->assertSame([], $result->getParameterNames());
    }

    public function testSuccessfulParseWithWarnings(): void
    {
        $parameters = [
            'data' => ['type' => 'string', 'required' => true],
        ];
        $warnings = [
            'Deprecated syntax detected in template',
            'Parameter type inference may be inaccurate',
        ];

        $result = new ParseResult(true, $parameters, $warnings);

        $this->assertTrue($result->success);
        $this->assertSame($parameters, $result->parameters);
        $this->assertSame($warnings, $result->warnings);
        $this->assertNull($result->error);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->hasWarnings());
        $this->assertSame(['data'], $result->getParameterNames());
    }

    public function testFailedParseWithException(): void
    {
        $error = new \RuntimeException('Template syntax error on line 5');
        $warnings = ['Could not determine parameter types'];

        $result = new ParseResult(false, [], $warnings, $error);

        $this->assertFalse($result->success);
        $this->assertSame([], $result->parameters);
        $this->assertSame($warnings, $result->warnings);
        $this->assertSame($error, $result->error);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->hasWarnings());
        $this->assertSame([], $result->getParameterNames());
    }

    public function testComplexParameterDefinitions(): void
    {
        $parameters = [
            'user_id' => [
                'type' => 'integer',
                'required' => true,
                'min' => 1,
                'description' => 'Unique user identifier',
            ],
            'preferences' => [
                'type' => 'array',
                'required' => false,
                'default' => [],
                'items' => ['type' => 'string'],
            ],
            'metadata' => [
                'type' => 'object',
                'required' => false,
                'properties' => [
                    'created_at' => ['type' => 'datetime'],
                    'source' => ['type' => 'string', 'enum' => ['web', 'mobile', 'api']],
                ],
            ],
            'score' => [
                'type' => 'number',
                'required' => false,
                'min' => 0.0,
                'max' => 100.0,
                'precision' => 2,
            ],
        ];

        $result = new ParseResult(true, $parameters);

        $this->assertSame($parameters, $result->parameters);
        $this->assertCount(4, $result->parameters);

        $parameterNames = $result->getParameterNames();
        $this->assertContains('user_id', $parameterNames);
        $this->assertContains('preferences', $parameterNames);
        $this->assertContains('metadata', $parameterNames);
        $this->assertContains('score', $parameterNames);

        // 验证特定参数的详细信息
        $this->assertSame('integer', $result->parameters['user_id']['type']);
        $this->assertTrue($result->parameters['user_id']['required']);
        $this->assertSame('array', $result->parameters['preferences']['type']);
        $this->assertFalse($result->parameters['preferences']['required']);
    }

    public function testParameterNamesOrder(): void
    {
        $parameters = [
            'third' => ['type' => 'string', 'required' => true],
            'first' => ['type' => 'integer', 'required' => false],
            'second' => ['type' => 'boolean', 'required' => true],
        ];

        $result = new ParseResult(true, $parameters);

        // 参数名称应该保持插入顺序
        $expectedOrder = ['third', 'first', 'second'];
        $this->assertSame($expectedOrder, $result->getParameterNames());
    }

    public function testHasWarningsDetection(): void
    {
        // 空数组情况
        $resultNoWarnings = new ParseResult(true, [], []);
        $this->assertFalse($resultNoWarnings->hasWarnings());

        // 有警告情况
        $resultWithWarnings = new ParseResult(true, [], ['Warning message']);
        $this->assertTrue($resultWithWarnings->hasWarnings());

        // 多个警告情况
        $resultMultipleWarnings = new ParseResult(true, [], ['Warning 1', 'Warning 2']);
        $this->assertTrue($resultMultipleWarnings->hasWarnings());
    }

    public function testEmptyParametersHandling(): void
    {
        $result = new ParseResult(true, []);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([], $result->parameters);
        $this->assertSame([], $result->getParameterNames());
        $this->assertCount(0, $result->getParameterNames());
    }

    public function testParameterTypesVariety(): void
    {
        $parameters = [
            'string_param' => ['type' => 'string'],
            'int_param' => ['type' => 'integer'],
            'float_param' => ['type' => 'float'],
            'bool_param' => ['type' => 'boolean'],
            'array_param' => ['type' => 'array'],
            'object_param' => ['type' => 'object'],
            'datetime_param' => ['type' => 'datetime'],
            'custom_param' => ['type' => 'custom_type'],
        ];

        $result = new ParseResult(true, $parameters);

        $this->assertCount(8, $result->parameters);
        $this->assertSame('string', $result->parameters['string_param']['type']);
        $this->assertSame('integer', $result->parameters['int_param']['type']);
        $this->assertSame('custom_type', $result->parameters['custom_param']['type']);
    }

    public function testExceptionHandling(): void
    {
        $innerException = new \InvalidArgumentException('Invalid template structure');
        $outerException = new \RuntimeException('Parse failed', 0, $innerException);

        $result = new ParseResult(false, [], [], $outerException);

        $this->assertSame($outerException, $result->error);
        $this->assertSame($innerException, $result->error->getPrevious());
        $this->assertSame('Parse failed', $result->error->getMessage());
    }

    public function testReadonlyProperties(): void
    {
        $result = new ParseResult(true, ['param' => ['type' => 'string']], ['warning']);

        // 验证属性是只读的
        $reflection = new \ReflectionClass($result);

        $successProperty = $reflection->getProperty('success');
        $this->assertTrue($successProperty->isReadOnly());

        $parametersProperty = $reflection->getProperty('parameters');
        $this->assertTrue($parametersProperty->isReadOnly());

        $warningsProperty = $reflection->getProperty('warnings');
        $this->assertTrue($warningsProperty->isReadOnly());

        $errorProperty = $reflection->getProperty('error');
        $this->assertTrue($errorProperty->isReadOnly());
    }

    public function testLargeParameterSet(): void
    {
        $parameters = [];
        for ($i = 1; $i <= 100; ++$i) {
            $parameters["param_{$i}"] = [
                'type' => 'string',
                'required' => 0 === $i % 2, // 偶数必需，奇数可选
            ];
        }

        $result = new ParseResult(true, $parameters);

        $this->assertCount(100, $result->parameters);
        $this->assertCount(100, $result->getParameterNames());
        $this->assertContains('param_1', $result->getParameterNames());
        $this->assertContains('param_100', $result->getParameterNames());

        // 验证第一个和最后一个参数
        $this->assertSame('string', $result->parameters['param_1']['type']);
        $this->assertFalse($result->parameters['param_1']['required']); // 奇数，应该是false
        $this->assertTrue($result->parameters['param_100']['required']); // 偶数，应该是true
    }
}
