<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Tourze\PromptManageBundle\DTO\ExecutionResult;
use Tourze\PromptManageBundle\DTO\RenderResult;
use Tourze\PromptManageBundle\DTO\TestContext;
use Tourze\PromptManageBundle\DTO\ValidationResult;
use Tourze\PromptManageBundle\Repository\PromptRepository;

/**
 * 提示词测试服务 - 系统核心业务逻辑
 *
 * Linus: "做一件事，并把它做好"
 * 集成所有韧性和扩展组件，提供统一的测试服务
 */
final readonly class TestingService implements TestingServiceInterface
{
    public function __construct(
        private PromptRepository $promptRepository,
        private TemplateEngineRegistry $engineRegistry,
        private ParameterSandbox $parameterSandbox,
        private TimeoutGuard $timeoutGuard,
        private TemplateRenderingCircuitBreaker $circuitBreaker,
    ) {
    }

    public function renderTemplate(string $template, array $parameters): string
    {
        // 创建测试上下文
        $context = new TestContext($template, $parameters);

        // 执行测试
        $result = $this->executeTest(0, 1, $parameters, $template);

        // 确保返回字符串类型
        if (isset($result['success']) && true === $result['success']) {
            $content = $result['content'] ?? $template;

            return is_string($content) ? $content : $template;
        }

        return $template;
    }

    public function executeTest(int $promptId, int $version, array $parameters, ?string $customTemplate = null): array
    {
        try {
            $template = $this->resolveTemplate($promptId, $version, $customTemplate);
            $sanitizeResult = $this->sanitizeParameters($parameters);

            if (!$sanitizeResult['validation']->isValid()) {
                return $this->createErrorResponse(
                    'Parameter validation failed: ' . implode(', ', $sanitizeResult['validation']->errors),
                    $sanitizeResult['validation']->warnings
                );
            }

            $executionResult = $this->executeRender($template, $sanitizeResult['parameters']);

            return $this->processRenderResult($executionResult, $sanitizeResult, $parameters);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'content' => $customTemplate ?? '',
            ];
        }
    }

    private function resolveTemplate(int $promptId, int $version, ?string $customTemplate): string
    {
        if (null !== $customTemplate) {
            return $customTemplate;
        }

        $testData = $this->getTestData($promptId, $version);
        if (!isset($testData['template'])) {
            $errorMessage = 'Unknown error';
            if (isset($testData['error']) && is_string($testData['error'])) {
                $errorMessage = $testData['error'];
            }
            throw new \RuntimeException('Failed to get template: ' . $errorMessage);
        }

        $template = $testData['template'];
        if (!is_string($template)) {
            throw new \RuntimeException('Invalid template type: expected string');
        }

        return $template;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array{parameters: array<string, mixed>, validation: ValidationResult}
     */
    private function sanitizeParameters(array $parameters): array
    {
        return $this->parameterSandbox->sanitize($parameters);
    }

    /**
     * @param array<string> $warnings
     * @return array{success: false, error: string, warnings: array<string>}
     */
    private function createErrorResponse(string $error, array $warnings = []): array
    {
        return [
            'success' => false,
            'error' => $error,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $cleanParameters
     */
    private function executeRender(string $template, array $cleanParameters): ExecutionResult
    {
        return $this->timeoutGuard->execute(function () use ($template, $cleanParameters) {
            return $this->circuitBreaker->execute(function () use ($template, $cleanParameters) {
                $engine = $this->engineRegistry->getBestAvailableEngine();

                return $engine->render($template, $cleanParameters);
            });
        });
    }

    /**
     * @param array{parameters: array<string, mixed>, validation: ValidationResult} $sanitizeResult
     * @param array<string, mixed> $originalParameters
     * @return array{success: bool, content: string, metadata?: array<string, mixed>, error?: string|null}
     */
    private function processRenderResult(ExecutionResult $executionResult, array $sanitizeResult, array $originalParameters): array
    {
        if (!$executionResult->isSuccess()) {
            return [
                'success' => false,
                'content' => '',
                'error' => $executionResult->getErrorMessage(),
                'metadata' => $executionResult->metadata,
            ];
        }

        $renderResult = $executionResult->content;
        if (!$renderResult instanceof RenderResult) {
            throw new \RuntimeException('Expected RenderResult from engine, got: ' . get_debug_type($renderResult));
        }

        return [
            'success' => $renderResult->isSuccess(),
            'content' => $renderResult->content,
            'metadata' => array_merge($renderResult->metadata, $executionResult->metadata, [
                'parameter_warnings' => $sanitizeResult['validation']->warnings,
                'sanitized_parameters' => count($sanitizeResult['parameters']),
                'original_parameters' => count($originalParameters),
            ]),
            'error' => $renderResult->error?->getMessage(),
        ];
    }

    public function getTestData(int $promptId, ?int $version = null): array
    {
        try {
            $prompt = $this->promptRepository->find($promptId);
            if (null === $prompt) {
                throw new \RuntimeException("Prompt with ID {$promptId} not found");
            }

            // 获取指定版本或当前版本
            if (null === $version) {
                $version = $prompt->getCurrentVersion();
            }

            $promptVersion = null;
            foreach ($prompt->getVersions() as $v) {
                if ($v->getVersion() === $version) {
                    $promptVersion = $v;
                    break;
                }
            }

            if (null === $promptVersion) {
                throw new \RuntimeException("Version {$version} not found for prompt {$promptId}");
            }

            // 提取参数
            $template = $promptVersion->getContent();
            $parameters = $this->extractParameters($template);

            return [
                'prompt_id' => $promptId,
                'version' => $version,
                'template' => $template,
                'parameters' => $parameters,
                'prompt_name' => $prompt->getName(),
                'change_note' => $promptVersion->getChangeNote(),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage(),
                'success' => false,
            ];
        }
    }

    public function extractParameters(string $template): array
    {
        try {
            $engine = $this->engineRegistry->getBestAvailableEngine();
            $result = $engine->parseTemplate($template);

            if (!$result->isSuccess()) {
                return [];
            }

            return $result->parameters;
        } catch (\Throwable $e) {
            // 降级到简单提取
            $extractor = new RegexParameterExtractor();
            $result = $extractor->extractParameters($template);

            return $result->isSuccess() ? $result->parameters : [];
        }
    }
}
