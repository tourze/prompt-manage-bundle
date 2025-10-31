<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\Service\ParameterSandbox;

/**
 * T22: 韧性机制测试 - ParameterSandbox安全隔离
 *
 * Linus: "安全不是功能，是设计原则"
 * @internal
 */
#[CoversClass(ParameterSandbox::class)]
final class ParameterSandboxTest extends TestCase
{
    private ParameterSandbox $sandbox;

    /**
     * 测试正常参数通过验证
     */
    #[Test]
    public function cleanParametersPassValidation(): void
    {
        $parameters = [
            'user_name' => 'john_doe',
            'message' => 'Hello world!',
            'count' => '42',
        ];

        $result = $this->sandbox->sanitize($parameters);

        $this->assertTrue($result['validation']->isValid());
        $this->assertSame($parameters, $result['parameters']);
        $this->assertEmpty($result['validation']->errors);
    }

    /**
     * 测试HTML标签被转义
     */
    #[Test]
    public function htmlTagsAreEscaped(): void
    {
        $parameters = [
            'content' => '<script>alert("xss")</script>',
            'title' => '<h1>Title</h1>',
        ];

        $result = $this->sandbox->sanitize($parameters);

        $this->assertTrue($result['validation']->isValid());
        $this->assertSame([
            'content' => '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            'title' => '&lt;h1&gt;Title&lt;/h1&gt;',
        ], $result['parameters']);
    }

    /**
     * 测试SQL注入尝试被阻止
     */
    #[Test]
    public function sqlInjectionAttemptsAreBlocked(): void
    {
        $parameters = [
            'user_id' => '1; DROP TABLE users; --',
            'search' => "' OR '1'='1",
        ];

        $result = $this->sandbox->sanitize($parameters);

        // 这些参数应该被转义，不应该直接拒绝
        $this->assertTrue($result['validation']->isValid());
        $this->assertNotSame($parameters, $result['parameters']);

        // 验证危险字符被转义（使用正确的断言方法）
        $userId = $result['parameters']['user_id'] ?? '';
        $search = $result['parameters']['search'] ?? '';

        $this->assertIsString($userId);
        $this->assertIsString($search);

        $this->assertStringNotContainsString('DROP TABLE', $userId);
        $this->assertStringNotContainsString("'1'='1", $search);
    }

    /**
     * 测试过长参数被截断并警告
     */
    #[Test]
    public function overlyLongParametersAreTruncated(): void
    {
        $longString = str_repeat('a', 10001); // 超过10KB的字符串
        $parameters = [
            'description' => $longString,
        ];

        $result = $this->sandbox->sanitize($parameters);

        $this->assertTrue($result['validation']->isValid());
        $this->assertNotEmpty($result['validation']->warnings);
        $this->assertStringContainsString('truncated', $result['validation']->warnings[0]);
        $description = $result['parameters']['description'] ?? '';
        $this->assertIsString($description);
        $this->assertLessThan(strlen($longString), strlen($description));
    }

    /**
     * 测试空值和null值处理
     */
    #[Test]
    public function nullAndEmptyValuesAreHandled(): void
    {
        $parameters = [
            'empty_string' => '',
            'null_value' => null,
            'zero' => '0',
            'false_string' => 'false',
        ];

        $result = $this->sandbox->sanitize($parameters);

        $this->assertTrue($result['validation']->isValid());
        $this->assertSame('', $result['parameters']['empty_string']);
        // null值被转换处理，检查是否为有效字符串
        $nullValue = $result['parameters']['null_value'] ?? '';
        $this->assertIsString($nullValue); // null被转换为字符串
        $this->assertSame('0', $result['parameters']['zero']);
        $this->assertSame('false', $result['parameters']['false_string']);
    }

    /**
     * 测试特殊字符处理
     */
    #[Test]
    public function specialCharactersAreHandled(): void
    {
        $parameters = [
            'unicode' => '测试中文 🚀 émojis',
            'symbols' => '@#$%^&*()_+-=[]{}|;:,.<>?',
            'quotes' => '"single\' and "double" quotes',
        ];

        $result = $this->sandbox->sanitize($parameters);

        $this->assertTrue($result['validation']->isValid());

        // Unicode字符应该保持不变
        $unicode = $result['parameters']['unicode'] ?? '';
        $quotes = $result['parameters']['quotes'] ?? '';

        $this->assertIsString($unicode);
        $this->assertIsString($quotes);

        $this->assertStringContainsString('测试中文', $unicode);
        $this->assertStringContainsString('🚀', $unicode);

        // 引号应该被转义
        $this->assertStringNotContainsString('"', $quotes);
        $this->assertStringContainsString('&quot;', $quotes);
    }

    /**
     * 测试参数类型限制
     */
    #[Test]
    public function parameterTypeRestrictions(): void
    {
        $parameters = [
            'array_param' => ['not', 'allowed'],
            'object_param' => new \stdClass(),
        ];

        $result = $this->sandbox->sanitize($parameters);

        // 非字符串类型应该被转换或警告
        $this->assertTrue($result['validation']->isValid());
        $this->assertNotEmpty($result['validation']->warnings);

        // 数组和对象应该被转换为字符串
        $this->assertIsString($result['parameters']['array_param']);
        $this->assertIsString($result['parameters']['object_param']);
    }

    /**
     * 测试模板注入防护
     */
    #[Test]
    public function templateInjectionProtection(): void
    {
        $parameters = [
            'template_code' => '{{ system("rm -rf /") }}',
            'twig_syntax' => '{% if true %}danger{% endif %}',
        ];

        $result = $this->sandbox->sanitize($parameters);

        $this->assertTrue($result['validation']->isValid());

        // 模板语法应该被转义
        $templateCode = $result['parameters']['template_code'] ?? '';
        $twigSyntax = $result['parameters']['twig_syntax'] ?? '';

        $this->assertIsString($templateCode);
        $this->assertIsString($twigSyntax);

        $this->assertStringNotContainsString('{{', $templateCode);
        $this->assertStringNotContainsString('{%', $twigSyntax);
    }

    /**
     * 测试大量参数的性能
     */
    #[Test]
    public function largeParameterSetPerformance(): void
    {
        $parameters = [];
        for ($i = 0; $i < 1000; ++$i) {
            $parameters["param_{$i}"] = "value_{$i}";
        }

        $startTime = microtime(true);
        $result = $this->sandbox->sanitize($parameters);
        $endTime = microtime(true);

        $this->assertTrue($result['validation']->isValid());
        $this->assertCount(1000, $result['parameters']);

        // 处理1000个参数应该在合理时间内完成（比如100ms内）
        $this->assertLessThan(0.1, $endTime - $startTime);
    }

    /**
     * 测试验证结果结构
     */
    #[Test]
    public function validationResultStructure(): void
    {
        $parameters = ['test' => 'value'];
        $result = $this->sandbox->sanitize($parameters);

        $this->assertArrayHasKey('parameters', $result);
        $this->assertArrayHasKey('validation', $result);

        $validation = $result['validation'];
        $this->assertTrue(method_exists($validation, 'isValid'));
        $this->assertIsArray($validation->errors);
        $this->assertIsArray($validation->warnings);
    }

    /**
     * 测试sanitize方法的完整功能
     */
    #[Test]
    public function testSanitize(): void
    {
        // 测试综合场景：包含HTML、SQL注入、模板注入、特殊字符等
        $parameters = [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'html_content' => '<script>alert("xss")</script><h1>Title</h1>',
            'sql_injection' => '1; DROP TABLE users; --',
            'template_injection' => '{{ system("rm -rf /") }}',
            'special_chars' => '测试中文 🚀 symbols @#$%',
            'long_text' => str_repeat('a', 15000), // 超过限制
            'array_data' => ['item1', 'item2'],
            'null_value' => null,
            'empty_string' => '',
        ];

        $result = $this->sandbox->sanitize($parameters);

        // 基本结构验证
        $this->assertArrayHasKey('parameters', $result);
        $this->assertArrayHasKey('validation', $result);

        $sanitizedParams = $result['parameters'];
        $validation = $result['validation'];

        // 验证清理结果
        $this->assertTrue($validation->isValid());

        // HTML被转义
        $htmlContent = $sanitizedParams['html_content'] ?? '';
        $this->assertIsString($htmlContent);
        $this->assertStringContainsString('&lt;script&gt;', $htmlContent);
        $this->assertStringNotContainsString('<script>', $htmlContent);

        // SQL注入被清理
        $sqlInjection = $sanitizedParams['sql_injection'] ?? '';
        $this->assertIsString($sqlInjection);
        $this->assertStringNotContainsString('DROP TABLE', $sqlInjection);

        // 模板注入被清理
        $templateInjection = $sanitizedParams['template_injection'] ?? '';
        $this->assertIsString($templateInjection);
        $this->assertStringNotContainsString('{{', $templateInjection);

        // 正常内容保留
        $this->assertSame('John Doe', $sanitizedParams['name']);
        $this->assertSame('test@example.com', $sanitizedParams['email']);

        // Unicode和特殊字符处理
        $specialChars = $sanitizedParams['special_chars'] ?? '';
        $this->assertIsString($specialChars);
        $this->assertStringContainsString('测试中文', $specialChars);
        $this->assertStringContainsString('🚀', $specialChars);

        // 长文本被截断并有警告
        $longText = $sanitizedParams['long_text'] ?? '';
        $this->assertIsString($longText);
        $this->assertLessThan(strlen($parameters['long_text']), strlen($longText));
        $this->assertNotEmpty($validation->warnings);

        // 数组被转换为字符串
        $this->assertIsString($sanitizedParams['array_data']);

        // null值被处理
        $this->assertIsString($sanitizedParams['null_value']);

        // 空字符串保留
        $this->assertSame('', $sanitizedParams['empty_string']);
    }

    protected function setUp(): void
    {
        $this->sandbox = new ParameterSandbox();
    }
}
