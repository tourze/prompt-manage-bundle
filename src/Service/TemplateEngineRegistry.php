<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

/**
 * 模板引擎注册表 - 管理所有可用的模板引擎
 *
 * Linus: "注册表模式 - 简单、可预测、易于调试"
 * 提供统一的引擎管理和选择机制，支持失败降级
 */
final class TemplateEngineRegistry
{
    /**
     * @var array<string, TemplateEngineInterface>
     */
    private array $engines = [];

    /**
     * @var array<string, int>
     */
    private array $priorities = [];

    private ?string $defaultEngine = null;

    public function __construct(
        private readonly FallbackTemplateEngine $fallbackEngine,
    ) {
    }

    /**
     * 注册模板引擎
     */
    public function register(TemplateEngineInterface $engine, bool $setAsDefault = false): void
    {
        $name = $engine->getName();
        $this->engines[$name] = $engine;

        // 如果没有设置优先级，使用默认值
        if (!isset($this->priorities[$name])) {
            $this->priorities[$name] = 0;
        }

        if ($setAsDefault || null === $this->defaultEngine) {
            $this->defaultEngine = $name;
        }
    }

    /**
     * 设置引擎优先级
     */
    public function setPriority(string $engineName, int $priority): void
    {
        if (!isset($this->engines[$engineName])) {
            throw new \RuntimeException("Template engine '{$engineName}' not found");
        }

        $this->priorities[$engineName] = $priority;
    }

    /**
     * 批量注册引擎
     *
     * @param array<TemplateEngineInterface> $engines
     * @param array<string, int>|null $priorities 可选的优先级映射
     */
    public function registerBatch(array $engines, ?array $priorities = null): void
    {
        foreach ($engines as $engine) {
            $this->register($engine);

            // 如果提供了优先级映射，则设置优先级
            if (null !== $priorities && isset($priorities[$engine->getName()])) {
                $this->setPriority($engine->getName(), $priorities[$engine->getName()]);
            }
        }
    }

    /**
     * 获取指定引擎
     *
     * @throws \RuntimeException 引擎不存在时抛出
     */
    public function getEngine(string $name): TemplateEngineInterface
    {
        if (!isset($this->engines[$name])) {
            throw new \RuntimeException("Template engine '{$name}' not found");
        }

        return $this->engines[$name];
    }

    /**
     * 获取默认引擎
     */
    public function getDefaultEngine(): TemplateEngineInterface
    {
        if (null === $this->defaultEngine) {
            throw new \RuntimeException('No default template engine configured');
        }

        return $this->engines[$this->defaultEngine];
    }

    /**
     * 获取最佳可用引擎（智能选择）
     */
    public function getBestAvailableEngine(): TemplateEngineInterface
    {
        $availableEngines = [];

        // 收集所有可用引擎
        foreach ($this->engines as $name => $engine) {
            if ($engine->isAvailable()) {
                $availableEngines[$name] = $engine;
            }
        }

        if ([] === $availableEngines) {
            // 最后使用降级引擎（包装为接口兼容）
            return new FallbackEngineAdapter($this->fallbackEngine);
        }

        // 按优先级排序（降序）
        uasort($availableEngines, function ($a, $b) {
            $priorityA = $this->priorities[$a->getName()] ?? 0;
            $priorityB = $this->priorities[$b->getName()] ?? 0;

            return $priorityB <=> $priorityA;
        });

        return reset($availableEngines);
    }

    /**
     * 获取最佳健康引擎
     */
    public function getBestHealthyEngine(): TemplateEngineInterface
    {
        // 与getBestAvailableEngine相同的逻辑，因为isAvailable已经检查了健康状态
        return $this->getBestAvailableEngine();
    }

    /**
     * 检查引擎是否已注册
     */
    public function hasEngine(string $name): bool
    {
        return isset($this->engines[$name]);
    }

    /**
     * 获取所有已注册的引擎名称
     *
     * @return array<string>
     */
    public function getEngineNames(): array
    {
        return array_keys($this->engines);
    }

    /**
     * 获取所有注册的引擎，按优先级排序
     *
     * @return array<string, TemplateEngineInterface>
     */
    public function getAllEngines(): array
    {
        $engines = $this->engines;

        // 按优先级排序（降序）
        uasort($engines, function ($a, $b) {
            $priorityA = $this->priorities[$a->getName()] ?? 0;
            $priorityB = $this->priorities[$b->getName()] ?? 0;

            return $priorityB <=> $priorityA;
        });

        return $engines;
    }

    /**
     * 获取注册表状态信息
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        $status = [
            'default_engine' => $this->defaultEngine,
            'total_engines' => count($this->engines),
            'available_engines' => count($this->getAvailableEngineNames()),
            'engines' => [],
        ];

        foreach ($this->engines as $name => $engine) {
            $status['engines'][$name] = [
                'name' => $engine->getName(),
                'version' => $engine->getVersion(),
                'available' => $engine->isAvailable(),
                'features' => $engine->getSupportedFeatures(),
            ];
        }

        return $status;
    }

    /**
     * 获取所有可用的引擎名称
     *
     * @return array<string>
     */
    public function getAvailableEngineNames(): array
    {
        $available = [];
        foreach ($this->engines as $name => $engine) {
            if ($engine->isAvailable()) {
                $available[] = $name;
            }
        }

        return $available;
    }

    /**
     * 移除已注册的引擎
     */
    public function remove(string $engineName): void
    {
        if (!isset($this->engines[$engineName])) {
            throw new \RuntimeException("Template engine '{$engineName}' not found");
        }

        unset($this->engines[$engineName], $this->priorities[$engineName]);

        // 如果移除的是默认引擎，重新选择默认引擎
        if ($this->defaultEngine === $engineName) {
            $this->defaultEngine = [] === $this->engines ? null : array_key_first($this->engines);
        }
    }

    /**
     * 获取所有健康的引擎
     *
     * @return array<string, TemplateEngineInterface>
     */
    public function getHealthyEngines(): array
    {
        $healthy = [];
        foreach ($this->engines as $name => $engine) {
            if ($engine->isAvailable()) {
                $healthy[$name] = $engine;
            }
        }

        return $healthy;
    }

    /**
     * 获取引擎统计信息
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $totalEngines = count($this->engines);
        $healthyEngines = count($this->getHealthyEngines());

        return [
            'total_engines' => $totalEngines,
            'healthy_engines' => $healthyEngines,
            'unhealthy_engines' => $totalEngines - $healthyEngines,
            'default_engine' => $this->defaultEngine,
            'availability_rate' => $totalEngines > 0 ? round(($healthyEngines / $totalEngines) * 100, 2) : 0,
        ];
    }
}
