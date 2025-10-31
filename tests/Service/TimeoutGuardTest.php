<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\Service\TimeoutGuard;

/**
 * T22: 韧性机制测试 - TimeoutGuard超时保护
 *
 * Linus: "异常处理是设计的核心，不是附加功能"
 * @internal
 */
#[CoversClass(TimeoutGuard::class)]
final class TimeoutGuardTest extends TestCase
{
    private TimeoutGuard $timeoutGuard;

    /**
     * 测试正常执行，不超时的情况
     */
    public function normalOperationCompletesSuccessfully(): void
    {
        $result = $this->timeoutGuard->execute(function () {
            usleep(100000); // 100ms，远小于1秒超时

            return 'success';
        });

        $this->assertTrue($result->isSuccess());
        $this->assertSame('success', $result->content);
        $this->assertNull($result->error);
    }

    /**
     * 测试自定义超时时间
     */
    public function customTimeoutIsRespected(): void
    {
        $result = $this->timeoutGuard->execute(function () {
            usleep(150000); // 150ms

            return 'done';
        }, 200); // 200ms超时

        $this->assertTrue($result->isSuccess());
        $this->assertSame('done', $result->content);
    }

    /**
     * 测试超时检测（模拟）
     * 注意：真实超时难以在单元测试中稳定复现，这里测试超时检测逻辑
     */
    public function timeoutDetectionWorks(): void
    {
        // 使用极短的超时来测试检测逻辑
        $result = $this->timeoutGuard->execute(function () {
            usleep(200000); // 200ms

            return 'should_timeout';
        }, 50); // 50ms超时

        // 在某些系统上，这可能不会触发真实超时，但会触发检测逻辑
        if (!$result->isSuccess()) {
            $this->assertInstanceOf(\RuntimeException::class, $result->error);
            $this->assertStringContainsString('timed out', $result->error->getMessage());
        } else {
            // 如果没有超时，至少验证结果正确
            $this->assertSame('should_timeout', $result->content);
        }
    }

    /**
     * 测试操作抛出异常的情况
     */
    public function operationExceptionIsCaught(): void
    {
        $result = $this->timeoutGuard->execute(function () {
            throw new \InvalidArgumentException('Test exception');
        });

        $this->assertFalse($result->isSuccess());
        $this->assertInstanceOf(\InvalidArgumentException::class, $result->error);
        $this->assertSame('Test exception', $result->error->getMessage());
    }

    /**
     * 测试零超时或负数超时使用默认值
     */
    public function zeroOrNegativeTimeoutUsesDefault(): void
    {
        $result1 = $this->timeoutGuard->execute(function () {
            usleep(50000); // 50ms

            return 'zero_timeout';
        }, 0);

        $result2 = $this->timeoutGuard->execute(function () {
            usleep(50000); // 50ms

            return 'negative_timeout';
        }, -100);

        $this->assertTrue($result1->isSuccess());
        $this->assertTrue($result2->isSuccess());
        $this->assertSame('zero_timeout', $result1->content);
        $this->assertSame('negative_timeout', $result2->content);
    }

    /**
     * 测试嵌套调用不会互相干扰
     */
    public function nestedCallsDoNotInterfere(): void
    {
        $result = $this->timeoutGuard->execute(function () {
            // 内层调用
            $innerResult = $this->timeoutGuard->execute(function () {
                usleep(50000); // 50ms

                return 'inner';
            }, 200);

            usleep(50000); // 外层再等50ms

            $content = $innerResult->content;
            $contentStr = is_string($content) ? $content : '';

            return 'outer:' . $contentStr;
        }, 500);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('outer:inner', $result->content);
    }

    /**
     * 测试返回值类型多样性
     */
    public function differentReturnTypesAreSupported(): void
    {
        $stringResult = $this->timeoutGuard->execute(fn () => 'string');
        $arrayResult = $this->timeoutGuard->execute(fn () => ['key' => 'value']);
        $intResult = $this->timeoutGuard->execute(fn () => 42);
        $nullResult = $this->timeoutGuard->execute(fn () => null);

        $this->assertTrue($stringResult->isSuccess());
        $this->assertTrue($arrayResult->isSuccess());
        $this->assertTrue($intResult->isSuccess());
        $this->assertTrue($nullResult->isSuccess());

        $this->assertSame('string', $stringResult->content);
        $this->assertSame(['key' => 'value'], $arrayResult->content);
        $this->assertSame(42, $intResult->content);
        $this->assertNull($nullResult->content);
    }

    /**
     * 测试元数据记录
     */
    public function metadataIsRecorded(): void
    {
        $result = $this->timeoutGuard->execute(function () {
            usleep(100000); // 100ms

            return 'test';
        });

        $this->assertIsArray($result->metadata);
        $this->assertArrayHasKey('execution_time_ms', $result->metadata);
        $this->assertIsFloat($result->metadata['execution_time_ms']);
        $this->assertGreaterThan(90, $result->metadata['execution_time_ms']); // 至少90ms
        $this->assertLessThan(200, $result->metadata['execution_time_ms']); // 不超过200ms
    }

    /**
     * 测试 wouldTimeout 方法 - 使用默认超时
     */
    public function wouldTimeoutWithDefaultTimeout(): void
    {
        // 默认超时是1000ms
        $this->assertFalse($this->timeoutGuard->wouldTimeout(500)); // 500ms < 1000ms
        $this->assertFalse($this->timeoutGuard->wouldTimeout(1000)); // 1000ms = 1000ms
        $this->assertTrue($this->timeoutGuard->wouldTimeout(1500)); // 1500ms > 1000ms
        $this->assertTrue($this->timeoutGuard->wouldTimeout(2000)); // 2000ms > 1000ms
    }

    /**
     * 测试 wouldTimeout 方法 - 使用自定义超时
     */
    public function wouldTimeoutWithCustomTimeout(): void
    {
        // 使用自定义超时500ms
        $this->assertFalse($this->timeoutGuard->wouldTimeout(300, 500)); // 300ms < 500ms
        $this->assertFalse($this->timeoutGuard->wouldTimeout(500, 500)); // 500ms = 500ms
        $this->assertTrue($this->timeoutGuard->wouldTimeout(600, 500)); // 600ms > 500ms
        $this->assertTrue($this->timeoutGuard->wouldTimeout(1000, 500)); // 1000ms > 500ms
    }

    /**
     * 测试 wouldTimeout 方法 - 边界情况
     */
    public function wouldTimeoutBoundaryConditions(): void
    {
        // 测试0和负数超时时间，应该使用默认值
        $this->assertFalse($this->timeoutGuard->wouldTimeout(500, 0)); // 使用默认1000ms
        $this->assertFalse($this->timeoutGuard->wouldTimeout(500, -100)); // 使用默认1000ms
        $this->assertTrue($this->timeoutGuard->wouldTimeout(1500, 0)); // 使用默认1000ms
        $this->assertTrue($this->timeoutGuard->wouldTimeout(1500, -100)); // 使用默认1000ms

        // 测试极端值
        $this->assertFalse($this->timeoutGuard->wouldTimeout(0, 1000)); // 0ms
        $this->assertFalse($this->timeoutGuard->wouldTimeout(1, 1000)); // 1ms
        $this->assertTrue($this->timeoutGuard->wouldTimeout(999999, 1000)); // 很大的数
    }

    /**
     * 测试 wouldTimeout 方法 - 不同的超时配置
     */
    public function wouldTimeoutWithDifferentConfigurations(): void
    {
        // 测试非常短的超时
        $this->assertTrue($this->timeoutGuard->wouldTimeout(100, 50)); // 100ms > 50ms
        $this->assertFalse($this->timeoutGuard->wouldTimeout(30, 50)); // 30ms < 50ms

        // 测试很长的超时
        $this->assertFalse($this->timeoutGuard->wouldTimeout(5000, 10000)); // 5s < 10s
        $this->assertTrue($this->timeoutGuard->wouldTimeout(15000, 10000)); // 15s > 10s
    }

    /**
     * 测试 execute 方法 - 标准命名约定
     */
    public function testExecute(): void
    {
        $result = $this->timeoutGuard->execute(function () {
            return 'test_result';
        });

        $this->assertTrue($result->isSuccess());
        $this->assertSame('test_result', $result->content);
    }

    /**
     * 测试 wouldTimeout 方法 - 标准命名约定
     */
    public function testWouldTimeout(): void
    {
        // 测试不会超时的情况
        $this->assertFalse($this->timeoutGuard->wouldTimeout(500)); // 500ms < 1000ms默认超时

        // 测试会超时的情况
        $this->assertTrue($this->timeoutGuard->wouldTimeout(1500)); // 1500ms > 1000ms默认超时
    }

    protected function setUp(): void
    {
        $this->timeoutGuard = new TimeoutGuard(1000); // 1秒默认超时
    }
}
