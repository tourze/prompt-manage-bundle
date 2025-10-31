<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\DTO\TestResult;

/**
 * @internal
 */
#[CoversClass(TestResult::class)]
final class TestResultTest extends TestCase
{
    public function testSuccessfulResult(): void
    {
        $content = 'Generated content';
        $metadata = ['execution_time' => 123, 'engine' => 'twig'];

        $result = new TestResult(true, $content, $metadata);

        $this->assertTrue($result->success);
        $this->assertSame($content, $result->content);
        $this->assertSame($metadata, $result->metadata);
        $this->assertNull($result->error);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertSame('', $result->getErrorMessage());
    }

    public function testFailedResultWithException(): void
    {
        $content = '';
        $error = new \RuntimeException('Template compilation failed');
        $metadata = ['error_code' => 500];

        $result = new TestResult(false, $content, $metadata, $error);

        $this->assertFalse($result->success);
        $this->assertSame($content, $result->content);
        $this->assertSame($metadata, $result->metadata);
        $this->assertSame($error, $result->error);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailure());
        $this->assertSame('Template compilation failed', $result->getErrorMessage());
    }

    public function testFailedResultWithoutException(): void
    {
        $content = 'Partial content';
        $metadata = ['warning' => 'Some parameters missing'];

        $result = new TestResult(false, $content, $metadata);

        $this->assertFalse($result->success);
        $this->assertSame($content, $result->content);
        $this->assertSame($metadata, $result->metadata);
        $this->assertNull($result->error);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailure());
        $this->assertSame('', $result->getErrorMessage());
    }

    public function testResultWithEmptyMetadata(): void
    {
        $result = new TestResult(true, 'content');

        $this->assertTrue($result->success);
        $this->assertSame('content', $result->content);
        $this->assertSame([], $result->metadata);
        $this->assertNull($result->error);
    }

    public function testResultWithComplexMetadata(): void
    {
        $metadata = [
            'execution_time' => 150.5,
            'memory_usage' => 1024,
            'engine_info' => [
                'name' => 'twig',
                'version' => '3.0',
                'extensions' => ['core', 'sandbox'],
            ],
            'parameters_used' => ['name', 'age', 'email'],
            'template_size' => 256,
        ];

        $result = new TestResult(true, 'Generated content', $metadata);

        $this->assertSame($metadata, $result->metadata);
        $this->assertSame(150.5, $result->metadata['execution_time']);
        $this->assertIsArray($result->metadata['engine_info']);
        $this->assertSame('twig', $result->metadata['engine_info']['name']);
    }

    public function testErrorMessageWithNestedExceptions(): void
    {
        $innerException = new \InvalidArgumentException('Invalid parameter type');
        $outerException = new \RuntimeException('Template processing failed', 0, $innerException);

        $result = new TestResult(false, '', [], $outerException);

        $this->assertSame('Template processing failed', $result->getErrorMessage());
        $this->assertSame($outerException, $result->error);
    }

    public function testReadonlyProperties(): void
    {
        $result = new TestResult(true, 'content', ['key' => 'value']);

        // 验证属性是只读的
        $reflection = new \ReflectionClass($result);

        $successProperty = $reflection->getProperty('success');
        $this->assertTrue($successProperty->isReadOnly());

        $contentProperty = $reflection->getProperty('content');
        $this->assertTrue($contentProperty->isReadOnly());

        $metadataProperty = $reflection->getProperty('metadata');
        $this->assertTrue($metadataProperty->isReadOnly());

        $errorProperty = $reflection->getProperty('error');
        $this->assertTrue($errorProperty->isReadOnly());
    }

    public function testSuccessAndFailureStates(): void
    {
        $successResult = new TestResult(true, 'success content');
        $failureResult = new TestResult(false, 'failure content');

        // 成功结果
        $this->assertTrue($successResult->isSuccess());
        $this->assertFalse($successResult->isFailure());

        // 失败结果
        $this->assertFalse($failureResult->isSuccess());
        $this->assertTrue($failureResult->isFailure());

        // 互斥性验证
        $this->assertNotSame($successResult->isSuccess(), $successResult->isFailure());
        $this->assertNotSame($failureResult->isSuccess(), $failureResult->isFailure());
    }
}
