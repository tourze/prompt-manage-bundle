<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Tourze\PromptManageBundle\DTO\RenderResult;

/**
 * Markdown格式化处理器 - 简单的Markdown格式化
 *
 * 将文本转换为基本的HTML格式，支持常见的Markdown语法
 */
final readonly class MarkdownFormatter implements ResultProcessorInterface
{
    public function process(RenderResult $result): RenderResult
    {
        if (!$result->isSuccess()) {
            return $result;
        }

        $formatted = $this->formatMarkdown($result->content);
        $metadata = array_merge($result->metadata, [
            'processed_by' => $this->getName(),
            'format' => 'html',
            'original_format' => 'markdown',
        ]);

        return new RenderResult(
            success: true,
            content: $formatted,
            metadata: $metadata,
            error: $result->error
        );
    }

    /**
     * 简单的Markdown到HTML转换
     */
    private function formatMarkdown(string $content): string
    {
        $lines = explode("\n", $content);
        $html = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            // 标题
            if (1 === preg_match('/^#{1,6}\s+(.+)$/', $line, $matches)) {
                $level = strlen(explode(' ', $line)[0]);
                $text = $matches[1];
                $html[] = "<h{$level}>{$text}</h{$level}>";
                continue;
            }

            // 粗体
            $line = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line) ?? $line;

            // 斜体
            $line = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $line) ?? $line;

            // 代码
            $line = preg_replace('/`(.+?)`/', '<code>$1</code>', $line) ?? $line;

            // 链接
            $line = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $line) ?? $line;

            $html[] = "<p>{$line}</p>";
        }

        return implode("\n", $html);
    }

    public function getName(): string
    {
        return 'markdown_formatter';
    }

    public function supports(RenderResult $result): bool
    {
        // 检查内容是否包含Markdown语法
        return $result->isSuccess() && $this->containsMarkdown($result->content);
    }

    /**
     * 检查内容是否包含Markdown语法
     */
    private function containsMarkdown(string $content): bool
    {
        $patterns = [
            '/^#{1,6}\s+/',  // 标题
            '/\*\*[^*]+\*\*/', // 粗体
            '/\*[^*]+\*/',    // 斜体
            '/`[^`]+`/',      // 代码
            '/\[[^\]]+\]\([^)]+\)/', // 链接
        ];

        foreach ($patterns as $pattern) {
            if (1 === preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    public function getPriority(): int
    {
        return 50; // 中等优先级
    }
}
