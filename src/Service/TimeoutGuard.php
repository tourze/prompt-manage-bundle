<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Tourze\PromptManageBundle\DTO\ExecutionResult;

/**
 * 超时保护机制 - 防止渲染过程死循环或过度计算
 *
 * Linus: "韧性设计不是可选的，而是必须的"
 * 所有可能长时间运行的操作都必须有超时保护
 */
final readonly class TimeoutGuard
{
    public function __construct(
        private int $defaultTimeoutMs = 5000,
    ) {
    }

    /**
     * 执行有超时保护的操作
     *
     * @template T
     * @param callable(): T $operation
     * @return ExecutionResult
     */
    public function execute(callable $operation, int $timeoutMs = 0): ExecutionResult
    {
        $timeout = $timeoutMs > 0 ? $timeoutMs : $this->defaultTimeoutMs;
        $startTime = microtime(true);

        // 设置最大执行时间
        $oldLimit = ini_get('max_execution_time');
        set_time_limit((int) ceil($timeout / 1000));

        try {
            $result = $operation();

            // 检查是否超时
            $elapsed = (microtime(true) - $startTime) * 1000;
            if ($elapsed > $timeout) {
                $error = new \RuntimeException("Operation timed out after {$elapsed}ms (limit: {$timeout}ms)");

                return new ExecutionResult(
                    success: false,
                    content: null,
                    metadata: ['execution_time_ms' => $elapsed],
                    error: $error
                );
            }

            return new ExecutionResult(
                success: true,
                content: $result,
                metadata: ['execution_time_ms' => $elapsed]
            );
        } catch (\Throwable $e) {
            $elapsed = (microtime(true) - $startTime) * 1000;

            return new ExecutionResult(
                success: false,
                content: null,
                metadata: ['execution_time_ms' => $elapsed],
                error: $e
            );
        } finally {
            // 恢复原始时间限制
            if (false !== $oldLimit && '' !== $oldLimit) {
                set_time_limit((int) $oldLimit);
            }
        }
    }

    /**
     * 检查操作是否可能超时
     */
    public function wouldTimeout(int $estimatedMs, int $timeoutMs = 0): bool
    {
        $timeout = $timeoutMs > 0 ? $timeoutMs : $this->defaultTimeoutMs;

        return $estimatedMs > $timeout;
    }
}
