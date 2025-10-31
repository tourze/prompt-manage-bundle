<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\Service\TemplateRenderingCircuitBreaker;

/**
 * @internal
 */
#[CoversClass(TemplateRenderingCircuitBreaker::class)]
final class TemplateRenderingCircuitBreakerTest extends TestCase
{
    private TemplateRenderingCircuitBreaker $circuitBreaker;

    protected function setUp(): void
    {
        $this->circuitBreaker = new TemplateRenderingCircuitBreaker(
            failureThreshold: 3,
            recoveryTimeSeconds: 2
        );
    }

    public function testExecuteSuccessfulOperation(): void
    {
        $result = $this->circuitBreaker->execute(function () {
            return 'success';
        });

        self::assertSame('success', $result);

        $status = $this->circuitBreaker->getStatus();
        self::assertSame('closed', $status['state']);
        self::assertSame(0, $status['failure_count']);
        self::assertTrue($status['can_execute']);
    }

    public function testExecuteFailingOperation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test failure');

        $this->circuitBreaker->execute(function () {
            throw new \RuntimeException('Test failure');
        });
    }

    public function testCircuitBreakerOpensAfterThresholdFailures(): void
    {
        // 触发3次失败以达到阈值
        for ($i = 0; $i < 3; ++$i) {
            try {
                $this->circuitBreaker->execute(function () use ($i) {
                    throw new \RuntimeException('Failure ' . ($i + 1));
                });
            } catch (\RuntimeException $e) {
                // 预期的异常
            }
        }

        $status = $this->circuitBreaker->getStatus();
        self::assertSame('open', $status['state']);
        self::assertSame(3, $status['failure_count']);
        self::assertFalse($status['can_execute']);
    }

    public function testCircuitBreakerRejectsWhenOpen(): void
    {
        // 先触发失败以打开熔断器
        for ($i = 0; $i < 3; ++$i) {
            try {
                $this->circuitBreaker->execute(function () {
                    throw new \RuntimeException('Failure');
                });
            } catch (\RuntimeException $e) {
                // 预期的异常
            }
        }

        // 现在应该拒绝操作
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circuit breaker is OPEN - operation rejected for system protection');

        $this->circuitBreaker->execute(function () {
            return 'should not execute';
        });
    }

    public function testCircuitBreakerRecoveryAfterTimeout(): void
    {
        // 先触发失败以打开熔断器
        for ($i = 0; $i < 3; ++$i) {
            try {
                $this->circuitBreaker->execute(function () {
                    throw new \RuntimeException('Failure');
                });
            } catch (\RuntimeException $e) {
                // 预期的异常
            }
        }

        // 验证熔断器已打开
        $status = $this->circuitBreaker->getStatus();
        self::assertSame('open', $status['state']);

        // 等待恢复时间
        sleep(3);

        // 现在应该允许一次尝试（半开状态）
        $result = $this->circuitBreaker->execute(function () {
            return 'recovery success';
        });

        self::assertSame('recovery success', $result);

        // 验证熔断器已关闭
        $status = $this->circuitBreaker->getStatus();
        self::assertSame('closed', $status['state']);
        self::assertSame(0, $status['failure_count']);
    }

    public function testHalfOpenStateFailureReopensCircuit(): void
    {
        // 先触发失败以打开熔断器
        for ($i = 0; $i < 3; ++$i) {
            try {
                $this->circuitBreaker->execute(function () {
                    throw new \RuntimeException('Failure');
                });
            } catch (\RuntimeException $e) {
                // 预期的异常
            }
        }

        // 等待恢复时间
        sleep(3);

        // 在半开状态下再次失败应该重新打开熔断器
        try {
            $this->circuitBreaker->execute(function () {
                throw new \RuntimeException('Half-open failure');
            });
        } catch (\RuntimeException $e) {
            // 预期的异常
        }

        $status = $this->circuitBreaker->getStatus();
        self::assertSame('open', $status['state']);
        self::assertGreaterThan(0, $status['failure_count']);
    }

    public function testReset(): void
    {
        // 先触发失败以打开熔断器
        for ($i = 0; $i < 3; ++$i) {
            try {
                $this->circuitBreaker->execute(function () {
                    throw new \RuntimeException('Failure');
                });
            } catch (\RuntimeException $e) {
                // 预期的异常
            }
        }

        // 验证熔断器已打开
        $status = $this->circuitBreaker->getStatus();
        self::assertSame('open', $status['state']);

        // 重置熔断器
        $this->circuitBreaker->reset();

        // 验证熔断器已关闭并重置
        $status = $this->circuitBreaker->getStatus();
        self::assertSame('closed', $status['state']);
        self::assertSame(0, $status['failure_count']);
        self::assertSame(0, $status['last_failure_time']);
        self::assertTrue($status['can_execute']);
    }

    public function testGetStatusStructure(): void
    {
        $status = $this->circuitBreaker->getStatus();

        self::assertArrayHasKey('state', $status);
        self::assertArrayHasKey('failure_count', $status);
        self::assertArrayHasKey('last_failure_time', $status);
        self::assertArrayHasKey('can_execute', $status);
        self::assertArrayHasKey('next_attempt_time', $status);

        self::assertIsBool($status['can_execute']);
        self::assertIsInt($status['failure_count']);
        self::assertIsInt($status['last_failure_time']);
        self::assertIsString($status['state']);
    }

    public function testNextAttemptTimeWhenOpen(): void
    {
        // 触发失败以打开熔断器
        for ($i = 0; $i < 3; ++$i) {
            try {
                $this->circuitBreaker->execute(function () {
                    throw new \RuntimeException('Failure');
                });
            } catch (\RuntimeException $e) {
                // 预期的异常
            }
        }

        $status = $this->circuitBreaker->getStatus();
        self::assertSame('open', $status['state']);
        self::assertIsInt($status['next_attempt_time']);
        self::assertGreaterThan(time(), $status['next_attempt_time']);
    }

    public function testNextAttemptTimeWhenClosed(): void
    {
        $status = $this->circuitBreaker->getStatus();
        self::assertSame('closed', $status['state']);
        self::assertNull($status['next_attempt_time']);
    }
}
