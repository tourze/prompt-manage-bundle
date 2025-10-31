<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\Service\RegexParameterExtractor;

/**
 * @internal
 */
#[CoversClass(RegexParameterExtractor::class)]
final class RegexParameterExtractorTest extends TestCase
{
    private RegexParameterExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new RegexParameterExtractor();
    }

    public function testGetName(): void
    {
        $this->assertEquals('regex', $this->extractor->getName());
    }

    public function testGetSupportedPatterns(): void
    {
        $patterns = $this->extractor->getSupportedPatterns();

        $this->assertContains('twig', $patterns);
        $this->assertContains('simple', $patterns);
        $this->assertContains('percent', $patterns);
        $this->assertContains('dollar', $patterns);
        $this->assertCount(4, $patterns);
    }

    public function testSupportsTwigSyntax(): void
    {
        $this->assertTrue($this->extractor->supports('Hello {{ name }}!'));
        $this->assertTrue($this->extractor->supports('{{ variable }} is here'));
        $this->assertTrue($this->extractor->supports('Mix {{ var1 }} and {{ var2 }}'));
    }

    public function testSupportsSimpleSyntax(): void
    {
        $this->assertTrue($this->extractor->supports('Hello {name}!'));
        $this->assertTrue($this->extractor->supports('{variable} is here'));
        $this->assertTrue($this->extractor->supports('Mix {var1} and {var2}'));
    }

    public function testSupportsPercentSyntax(): void
    {
        $this->assertTrue($this->extractor->supports('Hello %name%!'));
        $this->assertTrue($this->extractor->supports('%variable% is here'));
        $this->assertTrue($this->extractor->supports('Mix %var1% and %var2%'));
    }

    public function testSupportsDollarSyntax(): void
    {
        $this->assertTrue($this->extractor->supports('Hello $name!'));
        $this->assertTrue($this->extractor->supports('$variable is here'));
        $this->assertTrue($this->extractor->supports('Mix $var1 and $var2'));
    }

    public function testDoesNotSupportPlainText(): void
    {
        $this->assertFalse($this->extractor->supports('Just plain text'));
        $this->assertFalse($this->extractor->supports('No variables here'));
        $this->assertFalse($this->extractor->supports(''));
    }

    public function testExtractsTwigParameters(): void
    {
        $result = $this->extractor->extractParameters('Hello {{ name }}, your age is {{ age }}!');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name', $result->parameters);
        $this->assertArrayHasKey('age', $result->parameters);

        $this->assertEquals('string', $result->parameters['name']['type']);
        $this->assertTrue($result->parameters['name']['required']);
        $this->assertEquals('twig', $result->parameters['name']['pattern']);
        $this->assertEquals('name', $result->parameters['name']['source']);
    }

    public function testExtractsSimpleParameters(): void
    {
        $result = $this->extractor->extractParameters('Hello {name}, your score is {score}!');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name', $result->parameters);
        $this->assertArrayHasKey('score', $result->parameters);

        $this->assertEquals('simple', $result->parameters['name']['pattern']);
        $this->assertEquals('simple', $result->parameters['score']['pattern']);
    }

    public function testExtractsPercentParameters(): void
    {
        $result = $this->extractor->extractParameters('Hello %name%, your balance is %balance%!');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name', $result->parameters);
        $this->assertArrayHasKey('balance', $result->parameters);

        $this->assertEquals('percent', $result->parameters['name']['pattern']);
        $this->assertEquals('percent', $result->parameters['balance']['pattern']);
    }

    public function testExtractsDollarParameters(): void
    {
        $result = $this->extractor->extractParameters('Hello $name, your ID is $user_id');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name', $result->parameters);
        $this->assertArrayHasKey('user_id', $result->parameters);

        $this->assertEquals('dollar', $result->parameters['name']['pattern']);
        $this->assertEquals('dollar', $result->parameters['user_id']['pattern']);
    }

    public function testExtractsMixedPatterns(): void
    {
        $result = $this->extractor->extractParameters('{{ twig_var }}, {simple_var}, %percent_var%, $dollar_var');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('twig_var', $result->parameters);
        $this->assertArrayHasKey('simple_var', $result->parameters);
        $this->assertArrayHasKey('percent_var', $result->parameters);
        $this->assertArrayHasKey('dollar_var', $result->parameters);

        $this->assertEquals('twig', $result->parameters['twig_var']['pattern']);
        $this->assertEquals('simple', $result->parameters['simple_var']['pattern']);
        $this->assertEquals('percent', $result->parameters['percent_var']['pattern']);
        $this->assertEquals('dollar', $result->parameters['dollar_var']['pattern']);
    }

    public function testHandlesDuplicateParameters(): void
    {
        $result = $this->extractor->extractParameters('Hello {name}, goodbye {name}!');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name', $result->parameters);
        $this->assertCount(1, $result->parameters); // 去重后只有一个
    }

    public function testHandlesNestedProperties(): void
    {
        $result = $this->extractor->extractParameters('Hello {{ user.name }}, your email is {{ user.email }}');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('user', $result->parameters);
        $this->assertEquals('user.name', $result->parameters['user']['source']);
    }

    public function testHandlesVariableNamingConventions(): void
    {
        $result = $this->extractor->extractParameters('{{ valid_name }}, {{ _underscore }}, {{ CamelCase }}, {{ number123 }}');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('valid_name', $result->parameters);
        $this->assertArrayHasKey('_underscore', $result->parameters);
        $this->assertArrayHasKey('CamelCase', $result->parameters);
        $this->assertArrayHasKey('number123', $result->parameters);
    }

    public function testIgnoresInvalidVariableNames(): void
    {
        // 这些应该不被匹配，因为变量名格式不正确
        $result = $this->extractor->extractParameters('{{ 123invalid }}, {{ -dash }}, {{ space name }}');

        $this->assertTrue($result->isSuccess());
        $this->assertEmpty($result->parameters); // 没有有效的变量名
    }

    public function testHandlesWhitespaceInTwigSyntax(): void
    {
        $result = $this->extractor->extractParameters('{{name}}, {{ spaced }}, {{  lots_of_space  }}');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name', $result->parameters);
        $this->assertArrayHasKey('spaced', $result->parameters);
        $this->assertArrayHasKey('lots_of_space', $result->parameters);
    }

    public function testHandlesWhitespaceInSimpleSyntax(): void
    {
        $result = $this->extractor->extractParameters('{name}, { spaced }, {  lots_of_space  }');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name', $result->parameters);
        $this->assertArrayHasKey('spaced', $result->parameters);
        $this->assertArrayHasKey('lots_of_space', $result->parameters);
    }

    public function testHandlesEmptyTemplate(): void
    {
        $result = $this->extractor->extractParameters('');

        $this->assertTrue($result->isSuccess());
        $this->assertEmpty($result->parameters);
    }

    public function testHandlesTemplateWithoutParameters(): void
    {
        $result = $this->extractor->extractParameters('This is just plain text without any parameters.');

        $this->assertTrue($result->isSuccess());
        $this->assertEmpty($result->parameters);
    }

    public function testHandlesComplexTemplate(): void
    {
        $template = 'Dear {{ customer.name }},

Your order {order_id} has been processed.
Total amount: %total_amount%
Payment method: $payment_method

Thank you for shopping with us!';

        $result = $this->extractor->extractParameters($template);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('customer', $result->parameters);
        $this->assertArrayHasKey('order_id', $result->parameters);
        $this->assertArrayHasKey('total_amount', $result->parameters);
        $this->assertArrayHasKey('payment_method', $result->parameters);

        $this->assertEquals('customer.name', $result->parameters['customer']['source']);
        $this->assertEquals('twig', $result->parameters['customer']['pattern']);
        $this->assertEquals('simple', $result->parameters['order_id']['pattern']);
        $this->assertEquals('percent', $result->parameters['total_amount']['pattern']);
        $this->assertEquals('dollar', $result->parameters['payment_method']['pattern']);
    }

    public function testAllParametersHaveRequiredFields(): void
    {
        $result = $this->extractor->extractParameters('{{ name }}, {age}, %status%, $id');

        $this->assertTrue($result->isSuccess());

        foreach ($result->parameters as $param) {
            $this->assertArrayHasKey('type', $param);
            $this->assertArrayHasKey('required', $param);
            $this->assertArrayHasKey('pattern', $param);
            $this->assertArrayHasKey('source', $param);

            $this->assertEquals('string', $param['type']);
            $this->assertTrue($param['required']);
        }
    }

    public function testHandlesSpecialCharactersInContent(): void
    {
        // 模板中包含看起来像变量但不是的内容
        $result = $this->extractor->extractParameters('Email: user@{domain}.com, URL: http://{host}/path');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('domain', $result->parameters);
        $this->assertArrayHasKey('host', $result->parameters);
    }

    public function testNestedParameterExtraction(): void
    {
        $result = $this->extractor->extractParameters('{{ user.profile.name }} lives in {{ user.address.city }}');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('user', $result->parameters);
        $this->assertCount(1, $result->parameters); // 两个都归到 'user' 键下
        $this->assertEquals('user.profile.name', $result->parameters['user']['source']);
    }

    public function testLongVariableNames(): void
    {
        $result = $this->extractor->extractParameters('{{ very_long_variable_name_with_many_underscores }}');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('very_long_variable_name_with_many_underscores', $result->parameters);
    }

    public function testNumbersInVariableNames(): void
    {
        $result = $this->extractor->extractParameters('{{ var1 }}, {{ var_2 }}, {{ variable123 }}');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('var1', $result->parameters);
        $this->assertArrayHasKey('var_2', $result->parameters);
        $this->assertArrayHasKey('variable123', $result->parameters);
    }

    /**
     * 测试extractParameters方法的综合功能
     */
    public function testExtractParameters(): void
    {
        // 测试复杂模板，包含所有支持的参数模式
        $template = 'Hello {{ customer.name }},
            Your order {order_id} is ready.
            Total: %total_amount%
            Payment: $payment_method
            Status: {{ order.status }}
            Date: {created_date}
            Discount: %discount_percent%
            Reference: $ref_number';

        $result = $this->extractor->extractParameters($template);

        // 基本结构验证
        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->parameters);
        $this->assertNull($result->error);

        // 验证提取的参数
        $expectedParameters = [
            'customer' => ['type' => 'string', 'required' => true, 'pattern' => 'twig', 'source' => 'customer.name'],
            'order_id' => ['type' => 'string', 'required' => true, 'pattern' => 'simple', 'source' => 'order_id'],
            'total_amount' => ['type' => 'string', 'required' => true, 'pattern' => 'percent', 'source' => 'total_amount'],
            'payment_method' => ['type' => 'string', 'required' => true, 'pattern' => 'dollar', 'source' => 'payment_method'],
            'order' => ['type' => 'string', 'required' => true, 'pattern' => 'twig', 'source' => 'order.status'],
            'created_date' => ['type' => 'string', 'required' => true, 'pattern' => 'simple', 'source' => 'created_date'],
            'discount_percent' => ['type' => 'string', 'required' => true, 'pattern' => 'percent', 'source' => 'discount_percent'],
            'ref_number' => ['type' => 'string', 'required' => true, 'pattern' => 'dollar', 'source' => 'ref_number'],
        ];

        // 验证参数数量
        $this->assertCount(count($expectedParameters), $result->parameters);

        // 验证每个参数的详细信息
        foreach ($expectedParameters as $paramName => $expectedData) {
            $this->assertArrayHasKey($paramName, $result->parameters);
            $actualParam = $result->parameters[$paramName];

            $this->assertEquals($expectedData['type'], $actualParam['type']);
            $this->assertEquals($expectedData['required'], $actualParam['required']);
            $this->assertEquals($expectedData['pattern'], $actualParam['pattern']);
            $this->assertEquals($expectedData['source'], $actualParam['source']);
        }

        // 测试边界情况
        $emptyResult = $this->extractor->extractParameters('');
        $this->assertTrue($emptyResult->isSuccess());
        $this->assertEmpty($emptyResult->parameters);

        $plainTextResult = $this->extractor->extractParameters('No variables here');
        $this->assertTrue($plainTextResult->isSuccess());
        $this->assertEmpty($plainTextResult->parameters);

        // 测试重复变量去重
        $duplicateResult = $this->extractor->extractParameters('{{ name }} and {{ name }} again');
        $this->assertTrue($duplicateResult->isSuccess());
        $this->assertCount(1, $duplicateResult->parameters);
        $this->assertArrayHasKey('name', $duplicateResult->parameters);
    }
}
