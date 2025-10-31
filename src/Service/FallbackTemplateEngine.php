<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Tourze\PromptManageBundle\DTO\RenderResult;

/**
 * 降级模板引擎 - 当主引擎失败时的安全降级
 *
 * Linus: "系统永远不应该完全失败，总要有个Plan B"
 * 提供简单但可靠的模板渲染降级方案，确保系统在最坏情况下仍能工作
 */
final readonly class FallbackTemplateEngine
{
    /**
     * 使用简单字符串替换进行降级渲染
     *
     * 这是最后的防线，只支持 {{variable}} 格式的简单变量替换
     * 不支持复杂语法，但绝对不会失败
     *
     * @param array<string, mixed> $parameters
     */
    public function render(string $template, array $parameters): RenderResult
    {
        try {
            $content = $template;

            foreach ($parameters as $key => $value) {
                // 只处理标量值，保证安全
                if (is_scalar($value) || null === $value) {
                    $placeholder = '{{' . $key . '}}';
                    $replacement = null === $value ? '' : (string) $value;
                    $content = str_replace($placeholder, $replacement, $content);
                }
            }

            return new RenderResult(
                success: true,
                content: $content,
                metadata: [
                    'engine' => 'fallback',
                    'original_length' => strlen($template),
                    'result_length' => strlen($content),
                    'replacements_made' => $this->countReplacements($template, $parameters),
                ]
            );
        } catch (\Throwable $e) {
            // 即使降级也失败了，返回原始模板
            return new RenderResult(
                success: false,
                content: $template,
                metadata: ['engine' => 'fallback', 'emergency_mode' => true],
                error: $e
            );
        }
    }

    /**
     * 计算执行了多少次替换
     *
     * @param array<string, mixed> $parameters
     */
    private function countReplacements(string $template, array $parameters): int
    {
        $count = 0;
        foreach ($parameters as $key => $value) {
            if (is_scalar($value) || null === $value) {
                $placeholder = '{{' . $key . '}}';
                $count += substr_count($template, $placeholder);
            }
        }

        return $count;
    }

    /**
     * 提取模板中的变量占位符
     *
     * @return array<string>
     */
    public function extractVariables(string $template): array
    {
        $matches = [];
        preg_match_all('/\{\{(\w+)\}\}/', $template, $matches);

        return array_unique($matches[1]);
    }

    /**
     * 验证模板语法（简单版本）
     */
    public function isValidTemplate(string $template): bool
    {
        // 检查括号是否匹配
        $openCount = substr_count($template, '{{');
        $closeCount = substr_count($template, '}}');

        return $openCount === $closeCount;
    }
}
