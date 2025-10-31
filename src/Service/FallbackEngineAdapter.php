<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Tourze\PromptManageBundle\DTO\ParseResult;
use Tourze\PromptManageBundle\DTO\RenderResult;
use Tourze\PromptManageBundle\DTO\ValidationResult;

/**
 * 降级引擎适配器 - 将FallbackTemplateEngine适配为TemplateEngineInterface
 *
 * 适配器模式：让现有的降级引擎兼容统一接口
 */
final readonly class FallbackEngineAdapter implements TemplateEngineInterface
{
    public function __construct(
        private FallbackTemplateEngine $fallbackEngine,
    ) {
    }

    public function getName(): string
    {
        return 'fallback';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function parseTemplate(string $template): ParseResult
    {
        try {
            $variables = $this->fallbackEngine->extractVariables($template);
            $parameters = [];

            foreach ($variables as $variable) {
                $parameters[$variable] = ['type' => 'string', 'required' => true];
            }

            return new ParseResult(
                success: true,
                parameters: $parameters
            );
        } catch (\Throwable $e) {
            return new ParseResult(
                success: false,
                error: $e
            );
        }
    }

    public function render(string $template, array $parameters): RenderResult
    {
        return $this->fallbackEngine->render($template, $parameters);
    }

    public function validateTemplate(string $template): ValidationResult
    {
        $isValid = $this->fallbackEngine->isValidTemplate($template);

        return new ValidationResult(
            valid: $isValid,
            errors: $isValid ? [] : ['Template syntax validation failed'],
            metadata: ['engine' => 'fallback']
        );
    }

    public function isAvailable(): bool
    {
        return true; // 降级引擎总是可用
    }

    /**
     * @return array<string>
     */
    public function getSupportedFeatures(): array
    {
        return ['simple_variable_substitution'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return [
            'engine' => 'fallback',
            'safe_mode' => true,
            'features' => ['variable_substitution'],
            'max_complexity' => 'low',
        ];
    }
}
