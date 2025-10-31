<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\DTO\ExecutionResult;

/**
 * @internal
 */
#[CoversClass(ExecutionResult::class)]
final class ExecutionResultTest extends TestCase
{
    public function testSuccessfulResultWithStringContent(): void
    {
        $content = 'Execution completed successfully';
        $metadata = ['execution_time' => 100, 'memory_used' => 512];

        $result = new ExecutionResult(true, $content, $metadata);

        $this->assertTrue($result->success);
        $this->assertSame($content, $result->content);
        $this->assertSame($metadata, $result->metadata);
        $this->assertNull($result->error);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertSame('', $result->getErrorMessage());
    }

    public function testSuccessfulResultWithMixedContent(): void
    {
        $content = ['data' => 'value', 'count' => 42, 'items' => [1, 2, 3]];

        $result = new ExecutionResult(true, $content);

        $this->assertTrue($result->success);
        $this->assertSame($content, $result->content);
        $this->assertIsArray($result->content);
        $this->assertSame('value', $result->content['data']);
        $this->assertSame(42, $result->content['count']);
    }

    public function testFailedResultWithException(): void
    {
        $error = new \RuntimeException('Execution timeout occurred');
        $metadata = ['timeout_ms' => 5000, 'attempts' => 1];

        $result = new ExecutionResult(false, null, $metadata, $error);

        $this->assertFalse($result->success);
        $this->assertNull($result->content);
        $this->assertSame($metadata, $result->metadata);
        $this->assertSame($error, $result->error);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailure());
        $this->assertSame('Execution timeout occurred', $result->getErrorMessage());
    }

    public function testFailedResultWithPartialContent(): void
    {
        $content = 'Partial execution result';
        $metadata = ['warning' => 'Execution interrupted'];

        $result = new ExecutionResult(false, $content, $metadata);

        $this->assertFalse($result->success);
        $this->assertSame($content, $result->content);
        $this->assertSame($metadata, $result->metadata);
        $this->assertNull($result->error);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailure());
        $this->assertSame('', $result->getErrorMessage());
    }

    public function testResultWithNullContent(): void
    {
        $result = new ExecutionResult(true);

        $this->assertTrue($result->success);
        $this->assertNull($result->content);
        $this->assertSame([], $result->metadata);
        $this->assertNull($result->error);
    }

    public function testResultWithDifferentContentTypes(): void
    {
        // 测试不同类型的内容
        $testCases = [
            'string' => 'test string',
            'integer' => 42,
            'float' => 3.14,
            'array' => ['a', 'b', 'c'],
            'object' => new \stdClass(),
            'boolean' => true,
        ];

        foreach ($testCases as $type => $content) {
            $result = new ExecutionResult(true, $content);

            $this->assertTrue($result->success);
            $this->assertSame($content, $result->content);
            $this->assertTrue($result->isSuccess());
        }
    }

    public function testComplexMetadata(): void
    {
        $metadata = [
            'execution_info' => [
                'start_time' => '2023-01-01 10:00:00',
                'end_time' => '2023-01-01 10:00:05',
                'duration_ms' => 5000,
            ],
            'resource_usage' => [
                'memory_peak' => 1024 * 1024,
                'cpu_time' => 2.5,
            ],
            'context' => [
                'user_id' => 123,
                'session_id' => 'sess_456',
                'environment' => 'production',
            ],
        ];

        $result = new ExecutionResult(true, 'success', $metadata);

        $this->assertSame($metadata, $result->metadata);
        $this->assertSame(5000, $result->metadata['execution_info']['duration_ms']);
        $this->assertSame('production', $result->metadata['context']['environment']);
    }

    public function testExceptionHandling(): void
    {
        $innerException = new \InvalidArgumentException('Invalid parameter');
        $outerException = new \RuntimeException('Execution failed', 500, $innerException);

        $result = new ExecutionResult(false, null, [], $outerException);

        $this->assertSame('Execution failed', $result->getErrorMessage());
        $this->assertSame($outerException, $result->error);
        $this->assertSame($innerException, $result->error->getPrevious());
    }

    public function testReadonlyProperties(): void
    {
        $result = new ExecutionResult(true, 'content', ['key' => 'value']);

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

    public function testSuccessFailureMutualExclusion(): void
    {
        $successResult = new ExecutionResult(true);
        $failureResult = new ExecutionResult(false);

        // 成功和失败状态互斥
        $this->assertTrue($successResult->isSuccess());
        $this->assertFalse($successResult->isFailure());

        $this->assertFalse($failureResult->isSuccess());
        $this->assertTrue($failureResult->isFailure());

        // 确保没有同时为真或同时为假的情况
        $this->assertNotSame($successResult->isSuccess(), $successResult->isFailure());
        $this->assertNotSame($failureResult->isSuccess(), $failureResult->isFailure());
    }
}
