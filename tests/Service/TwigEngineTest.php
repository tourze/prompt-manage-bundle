<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\Service\TwigEngine;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @internal
 */
#[CoversClass(TwigEngine::class)]
final class TwigEngineTest extends TestCase
{
    private TwigEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new TwigEngine();
    }

    public function testGetName(): void
    {
        $this->assertEquals('twig', $this->engine->getName());
    }

    public function testGetVersion(): void
    {
        $this->assertEquals(Environment::VERSION, $this->engine->getVersion());
    }

    public function testIsAvailable(): void
    {
        $this->assertTrue($this->engine->isAvailable());
    }

    public function testGetSupportedFeatures(): void
    {
        $features = $this->engine->getSupportedFeatures();

        $expectedFeatures = [
            'variables',
            'filters',
            'functions',
            'conditionals',
            'loops',
            'includes',
            'macros',
            'inheritance',
            'auto_escape',
            'strict_variables',
        ];

        foreach ($expectedFeatures as $feature) {
            $this->assertContains($feature, $features);
        }
    }

    public function testGetConfiguration(): void
    {
        $config = $this->engine->getConfiguration();

        $this->assertEquals('twig', $config['engine']);
        $this->assertEquals(Environment::VERSION, $config['version']);
        $this->assertTrue($config['strict_variables']);
        $this->assertEquals('html', $config['autoescape']);
        $this->assertEquals(-1, $config['optimizations']);
        $this->assertArrayHasKey('features', $config);
    }

    public function testValidateValidTemplate(): void
    {
        $result = $this->engine->validateTemplate('Hello {{ name }}!');

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors);
        $this->assertEquals('twig', $result->metadata['engine']);
    }

    public function testValidateInvalidTemplate(): void
    {
        $result = $this->engine->validateTemplate('Hello {{ name }!'); // 缺少一个 }

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors);
        $this->assertEquals('twig', $result->metadata['engine']);
    }

    public function testParseValidTemplate(): void
    {
        $result = $this->engine->parseTemplate('Hello {{ name }}, your age is {{ age }}!');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name', $result->parameters);
        $this->assertArrayHasKey('age', $result->parameters);

        $this->assertEquals('string', $result->parameters['name']['type']);
        $this->assertTrue($result->parameters['name']['required']);
        $this->assertEquals('string', $result->parameters['age']['type']);
        $this->assertTrue($result->parameters['age']['required']);
    }

    public function testParseInvalidTemplate(): void
    {
        $result = $this->engine->parseTemplate('Hello {{ name }!'); // 语法错误

        $this->assertFalse($result->isSuccess());
        $this->assertNotNull($result->error);
    }

    public function testRenderSimpleTemplate(): void
    {
        $template = 'Hello {{ name }}!';
        $parameters = ['name' => 'World'];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Hello World!', $result->content);
        $this->assertEquals('twig', $result->metadata['engine']);
        $this->assertEquals(Environment::VERSION, $result->metadata['version']);
    }

    public function testRenderComplexTemplate(): void
    {
        $template = 'Hello {{ user.name }}! You have {{ items|length }} items.';
        $parameters = [
            'user' => ['name' => 'John'],
            'items' => ['apple', 'banana', 'orange'],
        ];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Hello John! You have 3 items.', $result->content);
    }

    public function testRenderWithMissingVariable(): void
    {
        $template = 'Hello {{ name }}!';
        $parameters = []; // 缺少 name 变量

        $result = $this->engine->render($template, $parameters);

        $this->assertFalse($result->isSuccess());
        $this->assertInstanceOf(\Throwable::class, $result->error);
        $this->assertEquals('runtime', $result->metadata['error_type']);
    }

    public function testRenderWithConditionals(): void
    {
        $template = '{% if user %}Hello {{ user }}!{% else %}Hello Guest!{% endif %}';

        // 测试有用户的情况
        $result1 = $this->engine->render($template, ['user' => 'John']);
        $this->assertTrue($result1->isSuccess());
        $this->assertEquals('Hello John!', $result1->content);

        // 测试没有用户的情况
        $result2 = $this->engine->render($template, ['user' => null]);
        $this->assertTrue($result2->isSuccess());
        $this->assertEquals('Hello Guest!', $result2->content);
    }

    public function testRenderWithLoops(): void
    {
        $template = '{% for item in items %}{{ item }}{% if not loop.last %}, {% endif %}{% endfor %}';
        $parameters = ['items' => ['apple', 'banana', 'orange']];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('apple, banana, orange', $result->content);
    }

    public function testRenderWithFilters(): void
    {
        $template = '{{ name|upper }} has {{ count|number_format }} items';
        $parameters = ['name' => 'john', 'count' => 1234.56];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('JOHN', $result->content);
        $this->assertStringContainsString('1,235', $result->content);
    }

    public function testAutoEscapeFeature(): void
    {
        $template = 'User input: {{ input }}';
        $parameters = ['input' => '<script>alert("xss")</script>'];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('&lt;script&gt;', $result->content);
        $this->assertStringNotContainsString('<script>', $result->content);
    }

    public function testRenderMetadata(): void
    {
        $template = 'Hello {{ name }}!';
        $parameters = ['name' => 'World'];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('engine', $result->metadata);
        $this->assertArrayHasKey('version', $result->metadata);
        $this->assertArrayHasKey('original_length', $result->metadata);
        $this->assertArrayHasKey('result_length', $result->metadata);

        $this->assertEquals(strlen($template), $result->metadata['original_length']);
        $this->assertEquals(strlen($result->content), $result->metadata['result_length']);
    }

    public function testComplexVariableExtraction(): void
    {
        $template = '{{ simple }}, {{ with_underscore }}, {{ number123 }}, {{ _leading }}';

        $result = $this->engine->parseTemplate($template);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('simple', $result->parameters);
        $this->assertArrayHasKey('with_underscore', $result->parameters);
        $this->assertArrayHasKey('number123', $result->parameters);
        $this->assertArrayHasKey('_leading', $result->parameters);
    }

    public function testIgnoresNonVariableExpressions(): void
    {
        // 这些是Twig表达式但不是简单变量
        // 使用有效的Twig语法：字符串字面量和数字
        $template = '{{ "string literal" }}, {{ 123 }}';

        $result = $this->engine->parseTemplate($template);

        $this->assertTrue($result->isSuccess());
        $this->assertEmpty($result->parameters); // 不应该提取出任何变量
    }

    public function testDuplicateVariablesAreDeduped(): void
    {
        $template = 'Hello {{ name }}, goodbye {{ name }}!';

        $result = $this->engine->parseTemplate($template);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name', $result->parameters);
        $this->assertCount(1, $result->parameters);
    }

    public function testEmptyTemplate(): void
    {
        $result = $this->engine->parseTemplate('');

        $this->assertTrue($result->isSuccess());
        $this->assertEmpty($result->parameters);
    }

    public function testPlainTextTemplate(): void
    {
        $result = $this->engine->parseTemplate('Just plain text without variables');

        $this->assertTrue($result->isSuccess());
        $this->assertEmpty($result->parameters);
    }

    public function testVariablesWithWhitespace(): void
    {
        $template = '{{name}}, {{ spaced }}, {{  lots_of_space  }}';

        $result = $this->engine->parseTemplate($template);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name', $result->parameters);
        $this->assertArrayHasKey('spaced', $result->parameters);
        $this->assertArrayHasKey('lots_of_space', $result->parameters);
    }

    public function testRenderingError(): void
    {
        $template = '{{ undefined_function() }}'; // 不存在的函数

        $result = $this->engine->render($template, []);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals($template, $result->content); // 返回原始模板
        $this->assertArrayHasKey('error_type', $result->metadata);
        $this->assertInstanceOf(\Throwable::class, $result->error);
    }

    public function testStrictVariablesMode(): void
    {
        // 在严格模式下，未定义的变量应该抛出错误
        $template = '{{ undefined_variable }}';

        $result = $this->engine->render($template, []);

        $this->assertFalse($result->isSuccess());
        $this->assertInstanceOf(\Throwable::class, $result->error);
    }

    public function testComplexTemplate(): void
    {
        $template = '
Dear {{ customer.name }},

{% if order.items %}
Your order contains:
{% for item in order.items %}
- {{ item.name }}: {{ item.price|number_format(2) }}
{% endfor %}

Total: {{ order.total|number_format(2) }}
{% else %}
Your order is empty.
{% endif %}

Thank you!';

        $parameters = [
            'customer' => ['name' => 'John Doe'],
            'order' => [
                'items' => [
                    ['name' => 'Product A', 'price' => 10.99],
                    ['name' => 'Product B', 'price' => 25.50],
                ],
                'total' => 36.49,
            ],
        ];

        $result = $this->engine->render($template, $parameters);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Dear John Doe', $result->content);
        $this->assertStringContainsString('Product A: 10.99', $result->content);
        $this->assertStringContainsString('Product B: 25.50', $result->content);
        $this->assertStringContainsString('Total: 36.49', $result->content);
    }

    /**
     * 测试parseTemplate方法的综合功能
     */
    public function testParseTemplate(): void
    {
        // 测试简单模板解析
        $simpleTemplate = 'Hello {{ name }}, welcome to {{ site }}!';
        $result = $this->engine->parseTemplate($simpleTemplate);

        $this->assertTrue($result->isSuccess());
        $this->assertNull($result->error);
        $this->assertArrayHasKey('name', $result->parameters);
        $this->assertArrayHasKey('site', $result->parameters);

        // 验证参数结构
        $this->assertEquals('string', $result->parameters['name']['type']);
        $this->assertTrue($result->parameters['name']['required']);
        $this->assertEquals('string', $result->parameters['site']['type']);
        $this->assertTrue($result->parameters['site']['required']);

        // 测试复杂模板（包含嵌套属性）
        $complexTemplate = 'User: {{ user.profile.name }}, Email: {{ user.email }}, Count: {{ stats.total }}';
        $complexResult = $this->engine->parseTemplate($complexTemplate);

        $this->assertTrue($complexResult->isSuccess());
        $this->assertArrayHasKey('user', $complexResult->parameters);
        $this->assertArrayHasKey('stats', $complexResult->parameters);

        // 测试语法错误的模板
        $invalidTemplate = 'Hello {{ name }!'; // 缺少闭合括号
        $errorResult = $this->engine->parseTemplate($invalidTemplate);

        $this->assertFalse($errorResult->isSuccess());
        $this->assertNotNull($errorResult->error);

        // 测试空模板
        $emptyResult = $this->engine->parseTemplate('');
        $this->assertTrue($emptyResult->isSuccess());
        $this->assertEmpty($emptyResult->parameters);

        // 测试纯文本模板
        $plainTextResult = $this->engine->parseTemplate('Just plain text without variables');
        $this->assertTrue($plainTextResult->isSuccess());
        $this->assertEmpty($plainTextResult->parameters);

        // 测试包含表达式但不是变量的模板
        $expressionTemplate = '{{ "hello" }}, {{ 123 }}';
        $expressionResult = $this->engine->parseTemplate($expressionTemplate);
        $this->assertTrue($expressionResult->isSuccess());
        $this->assertEmpty($expressionResult->parameters); // 不应该提取出变量

        // 测试重复变量去重
        $duplicateTemplate = 'Hello {{ name }}, goodbye {{ name }}!';
        $duplicateResult = $this->engine->parseTemplate($duplicateTemplate);
        $this->assertTrue($duplicateResult->isSuccess());
        $this->assertCount(1, $duplicateResult->parameters);
        $this->assertArrayHasKey('name', $duplicateResult->parameters);
    }

    /**
     * 测试validateTemplate方法的综合功能
     */
    public function testValidateTemplate(): void
    {
        // 测试有效模板
        $validTemplate = 'Hello {{ name }}, your balance is {{ account.balance }}!';
        $validResult = $this->engine->validateTemplate($validTemplate);

        $this->assertTrue($validResult->isValid());
        $this->assertEmpty($validResult->errors);
        $this->assertEquals('twig', $validResult->metadata['engine']);

        // 测试语法错误模板
        $syntaxErrorTemplate = 'Hello {{ name }!'; // 缺少闭合括号
        $syntaxErrorResult = $this->engine->validateTemplate($syntaxErrorTemplate);

        $this->assertFalse($syntaxErrorResult->isValid());
        $this->assertNotEmpty($syntaxErrorResult->errors);
        $this->assertEquals('twig', $syntaxErrorResult->metadata['engine']);

        // 测试更复杂的语法错误
        $complexErrorTemplate = '{% if user %}Hello {{ user.name }{% endif %}'; // 缺少闭合 }}
        $complexErrorResult = $this->engine->validateTemplate($complexErrorTemplate);

        $this->assertFalse($complexErrorResult->isValid());
        $this->assertNotEmpty($complexErrorResult->errors);

        // 测试空模板
        $emptyResult = $this->engine->validateTemplate('');
        $this->assertTrue($emptyResult->isValid());
        $this->assertEmpty($emptyResult->errors);

        // 测试纯文本模板
        $plainTextResult = $this->engine->validateTemplate('Just plain text');
        $this->assertTrue($plainTextResult->isValid());
        $this->assertEmpty($plainTextResult->errors);

        // 测试包含循环和条件的复杂模板
        $complexValidTemplate = '
            {% if users %}
                <ul>
                {% for user in users %}
                    <li>{{ user.name }} - {{ user.email }}</li>
                {% endfor %}
                </ul>
            {% else %}
                <p>No users found</p>
            {% endif %}
        ';
        $complexValidResult = $this->engine->validateTemplate($complexValidTemplate);

        $this->assertTrue($complexValidResult->isValid());
        $this->assertEmpty($complexValidResult->errors);

        // 测试包含过滤器的模板
        $filterTemplate = '{{ name|upper }}, {{ date|date("Y-m-d") }}, {{ items|length }}';
        $filterResult = $this->engine->validateTemplate($filterTemplate);

        $this->assertTrue($filterResult->isValid());
        $this->assertEmpty($filterResult->errors);

        // 测试无效的过滤器语法
        $invalidFilterTemplate = '{{ name|invalid_filter( }}'; // 语法错误
        $invalidFilterResult = $this->engine->validateTemplate($invalidFilterTemplate);

        $this->assertFalse($invalidFilterResult->isValid());
        $this->assertNotEmpty($invalidFilterResult->errors);
    }
}
