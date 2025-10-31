<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Tourze\PromptManageBundle\DTO\RenderResult;

/**
 * HTML清理处理器 - 清理和安全化HTML内容
 *
 * 防止XSS攻击，确保输出内容的安全性
 */
final readonly class HtmlSanitizer implements ResultProcessorInterface
{
    public function process(RenderResult $result): RenderResult
    {
        if (!$result->isSuccess()) {
            return $result;
        }

        $sanitized = $this->sanitizeHtml($result->content);
        $metadata = array_merge($result->metadata, [
            'processed_by' => $this->getName(),
            'original_length' => strlen($result->content),
            'sanitized_length' => strlen($sanitized),
        ]);

        return new RenderResult(
            success: true,
            content: $sanitized,
            metadata: $metadata,
            error: $result->error
        );
    }

    /**
     * 清理HTML内容
     */
    private function sanitizeHtml(string $content): string
    {
        // 移除潜在危险的标签和属性
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $content) ?? $content;
        $content = preg_replace('/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/i', '', $content) ?? $content;
        $content = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $content) ?? $content;
        $content = preg_replace('/javascript:/i', '', $content) ?? $content;

        // HTML实体编码
        return htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }

    public function getName(): string
    {
        return 'html_sanitizer';
    }

    public function supports(RenderResult $result): bool
    {
        // 支持所有成功的结果
        return $result->isSuccess();
    }

    public function getPriority(): int
    {
        return 100; // 高优先级，安全处理应该优先
    }
}
