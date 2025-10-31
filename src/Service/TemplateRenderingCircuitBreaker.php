<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

/**
 * 模板渲染熔断器 - 失败保护机制
 *
 * Linus: "系统必须优雅地处理失败，而不是崩溃"
 * 当渲染连续失败时，自动熔断保护系统，避免雪崩效应
 */
final class TemplateRenderingCircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private string $state = self::STATE_CLOSED;

    private int $failureCount = 0;

    private int $lastFailureTime = 0;

    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeSeconds = 60,
    ) {
    }

    /**
     * 执行受保护的渲染操作
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws \RuntimeException 熔断器开启或操作失败时抛出
     */
    public function execute(callable $operation)
    {
        if (!$this->canExecute()) {
            throw new \RuntimeException('Circuit breaker is OPEN - operation rejected for system protection');
        }

        try {
            $result = $operation();
            $this->onSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->onFailure();
            throw $e;
        }
    }

    /**
     * 检查是否可以执行操作
     */
    private function canExecute(): bool
    {
        switch ($this->state) {
            case self::STATE_CLOSED:
                return true;

            case self::STATE_OPEN:
                if ($this->shouldAttemptRecovery()) {
                    $this->state = self::STATE_HALF_OPEN;

                    return true;
                }

                return false;

            case self::STATE_HALF_OPEN:
                return true;

            default:
                return false;
        }
    }

    /**
     * 检查是否应该尝试恢复
     */
    private function shouldAttemptRecovery(): bool
    {
        return (time() - $this->lastFailureTime) >= $this->recoveryTimeSeconds;
    }

    /**
     * 操作成功时的回调
     */
    private function onSuccess(): void
    {
        $this->failureCount = 0;
        $this->state = self::STATE_CLOSED;
    }

    /**
     * 操作失败时的回调
     */
    private function onFailure(): void
    {
        ++$this->failureCount;
        $this->lastFailureTime = time();

        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
        }
    }

    /**
     * 获取熔断器状态信息
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        return [
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'last_failure_time' => $this->lastFailureTime,
            'can_execute' => $this->canExecute(),
            'next_attempt_time' => self::STATE_OPEN === $this->state
                ? $this->lastFailureTime + $this->recoveryTimeSeconds
                : null,
        ];
    }

    /**
     * 手动重置熔断器（用于维护）
     */
    public function reset(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->lastFailureTime = 0;
    }
}
