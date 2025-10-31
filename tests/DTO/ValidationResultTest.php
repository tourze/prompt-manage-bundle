<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\DTO\ValidationResult;

/**
 * @internal
 */
#[CoversClass(ValidationResult::class)]
final class ValidationResultTest extends TestCase
{
    public function testValidResultWithoutMessages(): void
    {
        $result = new ValidationResult(true);

        $this->assertTrue($result->valid);
        $this->assertSame([], $result->errors);
        $this->assertSame([], $result->warnings);
        $this->assertSame([], $result->metadata);

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->hasErrors());
        $this->assertFalse($result->hasWarnings());
        $this->assertSame([], $result->getAllMessages());
    }

    public function testValidResultWithWarnings(): void
    {
        $warnings = ['Deprecated syntax detected', 'Consider using newer template features'];
        $metadata = ['checked_rules' => 15, 'processing_time' => 120];

        $result = new ValidationResult(true, [], $warnings, $metadata);

        $this->assertTrue($result->valid);
        $this->assertSame([], $result->errors);
        $this->assertSame($warnings, $result->warnings);
        $this->assertSame($metadata, $result->metadata);

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->hasErrors());
        $this->assertTrue($result->hasWarnings());
        $this->assertSame($warnings, $result->getAllMessages());
    }

    public function testInvalidResultWithErrors(): void
    {
        $errors = ['Syntax error: unclosed tag', 'Unknown filter: invalid_filter'];
        $metadata = ['parser' => 'twig', 'line_count' => 10];

        $result = new ValidationResult(false, $errors, [], $metadata);

        $this->assertFalse($result->valid);
        $this->assertSame($errors, $result->errors);
        $this->assertSame([], $result->warnings);
        $this->assertSame($metadata, $result->metadata);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasErrors());
        $this->assertFalse($result->hasWarnings());
        $this->assertSame($errors, $result->getAllMessages());
    }

    public function testInvalidResultWithErrorsAndWarnings(): void
    {
        $errors = ['Critical syntax error'];
        $warnings = ['Performance warning: complex expression', 'Style warning: inconsistent spacing'];
        $metadata = ['validation_level' => 'strict'];

        $result = new ValidationResult(false, $errors, $warnings, $metadata);

        $this->assertFalse($result->valid);
        $this->assertSame($errors, $result->errors);
        $this->assertSame($warnings, $result->warnings);
        $this->assertSame($metadata, $result->metadata);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasErrors());
        $this->assertTrue($result->hasWarnings());

        $expectedAllMessages = array_merge($errors, $warnings);
        $this->assertSame($expectedAllMessages, $result->getAllMessages());
        $this->assertCount(3, $result->getAllMessages());
    }

    public function testEmptyArraysHandling(): void
    {
        $result = new ValidationResult(true, [], [], []);

        $this->assertFalse($result->hasErrors());
        $this->assertFalse($result->hasWarnings());
        $this->assertSame([], $result->getAllMessages());
    }

    public function testComplexMetadata(): void
    {
        $metadata = [
            'validation_config' => [
                'strict_mode' => true,
                'security_checks' => true,
                'performance_analysis' => false,
            ],
            'template_info' => [
                'size_bytes' => 1024,
                'line_count' => 25,
                'complexity_score' => 3.2,
            ],
            'timing' => [
                'start_time' => '2023-01-01T10:00:00Z',
                'end_time' => '2023-01-01T10:00:01Z',
                'duration_ms' => 1000,
            ],
        ];

        $result = new ValidationResult(true, [], [], $metadata);

        $this->assertSame($metadata, $result->metadata);
        $this->assertTrue($result->metadata['validation_config']['strict_mode']);
        $this->assertSame(25, $result->metadata['template_info']['line_count']);
        $this->assertSame(1000, $result->metadata['timing']['duration_ms']);
    }

    public function testGetAllMessagesOrder(): void
    {
        $errors = ['Error 1', 'Error 2'];
        $warnings = ['Warning 1', 'Warning 2', 'Warning 3'];

        $result = new ValidationResult(false, $errors, $warnings);

        $allMessages = $result->getAllMessages();

        // 验证错误信息在前，警告信息在后
        $this->assertSame('Error 1', $allMessages[0]);
        $this->assertSame('Error 2', $allMessages[1]);
        $this->assertSame('Warning 1', $allMessages[2]);
        $this->assertSame('Warning 2', $allMessages[3]);
        $this->assertSame('Warning 3', $allMessages[4]);
        $this->assertCount(5, $allMessages);
    }

    public function testMultipleErrorsAndWarnings(): void
    {
        $errors = [
            'Syntax error on line 5',
            'Undefined variable: $unknown',
            'Invalid function call: nonexistent()',
        ];
        $warnings = [
            'Deprecated tag usage',
            'Long expression detected',
        ];

        $result = new ValidationResult(false, $errors, $warnings);

        $this->assertCount(3, $result->errors);
        $this->assertCount(2, $result->warnings);
        $this->assertCount(5, $result->getAllMessages());

        $this->assertTrue($result->hasErrors());
        $this->assertTrue($result->hasWarnings());
        $this->assertFalse($result->isValid());
    }

    public function testReadonlyProperties(): void
    {
        $result = new ValidationResult(true, ['error'], ['warning'], ['meta' => 'data']);

        // 验证属性是只读的
        $reflection = new \ReflectionClass($result);

        $validProperty = $reflection->getProperty('valid');
        $this->assertTrue($validProperty->isReadOnly());

        $errorsProperty = $reflection->getProperty('errors');
        $this->assertTrue($errorsProperty->isReadOnly());

        $warningsProperty = $reflection->getProperty('warnings');
        $this->assertTrue($warningsProperty->isReadOnly());

        $metadataProperty = $reflection->getProperty('metadata');
        $this->assertTrue($metadataProperty->isReadOnly());
    }

    public function testBooleanMethods(): void
    {
        // 测试各种状态组合
        $testCases = [
            // valid, has_errors, has_warnings
            [true, false, false],  // 完全正确
            [true, false, true],   // 有警告但有效
            [false, true, false],  // 有错误无效
            [false, true, true],   // 有错误和警告无效
        ];

        foreach ($testCases as [$isValid, $hasErrors, $hasWarnings]) {
            $errors = $hasErrors ? ['Error message'] : [];
            $warnings = $hasWarnings ? ['Warning message'] : [];

            $result = new ValidationResult($isValid, $errors, $warnings);

            $this->assertSame($isValid, $result->isValid());
            $this->assertSame($hasErrors, $result->hasErrors());
            $this->assertSame($hasWarnings, $result->hasWarnings());
        }
    }
}
