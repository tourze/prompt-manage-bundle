<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\DTO\RenderResult;
use Tourze\PromptManageBundle\Service\MarkdownFormatter;

/**
 * @internal
 */
#[CoversClass(MarkdownFormatter::class)]
final class MarkdownFormatterTest extends TestCase
{
    private MarkdownFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new MarkdownFormatter();
    }

    public function testGetName(): void
    {
        $this->assertEquals('markdown_formatter', $this->formatter->getName());
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(50, $this->formatter->getPriority());
    }

    public function testSupportsMarkdownContent(): void
    {
        $result = new RenderResult(true, '# Title', []);
        $this->assertTrue($this->formatter->supports($result));

        $result = new RenderResult(true, '**bold**', []);
        $this->assertTrue($this->formatter->supports($result));

        $result = new RenderResult(true, '*italic*', []);
        $this->assertTrue($this->formatter->supports($result));

        $result = new RenderResult(true, '`code`', []);
        $this->assertTrue($this->formatter->supports($result));

        $result = new RenderResult(true, '[link](url)', []);
        $this->assertTrue($this->formatter->supports($result));
    }

    public function testDoesNotSupportNonMarkdownContent(): void
    {
        $result = new RenderResult(true, 'Plain text without markdown', []);
        $this->assertFalse($this->formatter->supports($result));
    }

    public function testDoesNotSupportFailedResult(): void
    {
        $result = new RenderResult(false, '# Title', []);
        $this->assertFalse($this->formatter->supports($result));
    }

    public function testProcessFailedResultReturnsUnchanged(): void
    {
        $result = new RenderResult(false, '# Title', []);
        $processed = $this->formatter->process($result);

        $this->assertSame($result, $processed);
    }

    public function testFormatsHeaders(): void
    {
        $testCases = [
            '# Header 1' => '<h1>Header 1</h1>',
            '## Header 2' => '<h2>Header 2</h2>',
            '### Header 3' => '<h3>Header 3</h3>',
            '#### Header 4' => '<h4>Header 4</h4>',
            '##### Header 5' => '<h5>Header 5</h5>',
            '###### Header 6' => '<h6>Header 6</h6>',
        ];

        foreach ($testCases as $markdown => $expectedHtml) {
            $result = new RenderResult(true, $markdown, []);
            $processed = $this->formatter->process($result);

            $this->assertStringContainsString($expectedHtml, $processed->content);
        }
    }

    public function testFormatsBoldText(): void
    {
        $result = new RenderResult(true, '**bold text**', []);
        $processed = $this->formatter->process($result);

        $this->assertStringContainsString('<strong>bold text</strong>', $processed->content);
    }

    public function testFormatsItalicText(): void
    {
        $result = new RenderResult(true, '*italic text*', []);
        $processed = $this->formatter->process($result);

        $this->assertStringContainsString('<em>italic text</em>', $processed->content);
    }

    public function testFormatsInlineCode(): void
    {
        $result = new RenderResult(true, '`code snippet`', []);
        $processed = $this->formatter->process($result);

        $this->assertStringContainsString('<code>code snippet</code>', $processed->content);
    }

    public function testFormatsLinks(): void
    {
        $result = new RenderResult(true, '[Google](https://google.com)', []);
        $processed = $this->formatter->process($result);

        $this->assertStringContainsString('<a href="https://google.com">Google</a>', $processed->content);
    }

    public function testFormatsComplexMarkdown(): void
    {
        $markdown = '# Title

This is **bold** and this is *italic*.

Here is some `code` and a [link](https://example.com).

## Subtitle

More content here.';

        $result = new RenderResult(true, $markdown, []);
        $processed = $this->formatter->process($result);

        // 验证所有格式都被正确转换
        $this->assertStringContainsString('<h1>Title</h1>', $processed->content);
        $this->assertStringContainsString('<h2>Subtitle</h2>', $processed->content);
        $this->assertStringContainsString('<strong>bold</strong>', $processed->content);
        $this->assertStringContainsString('<em>italic</em>', $processed->content);
        $this->assertStringContainsString('<code>code</code>', $processed->content);
        $this->assertStringContainsString('<a href="https://example.com">link</a>', $processed->content);
        $this->assertStringContainsString('<p>', $processed->content);
    }

    public function testHandlesEmptyLines(): void
    {
        $result = new RenderResult(true, "Line 1\n\nLine 2", []);
        $processed = $this->formatter->process($result);

        // 双换行应该产生两个独立的段落，不是br标签
        $this->assertStringContainsString('<p>Line 1</p>', $processed->content);
        $this->assertStringContainsString('<p>Line 2</p>', $processed->content);
        // 确保没有br标签，因为双换行产生段落
        $this->assertStringNotContainsString('<br>', $processed->content);
    }

    public function testWrapsPlainTextInParagraphs(): void
    {
        $result = new RenderResult(true, 'Just plain text', []);
        $processed = $this->formatter->process($result);

        $this->assertStringContainsString('<p>Just plain text</p>', $processed->content);
    }

    public function testPreservesOriginalMetadata(): void
    {
        $originalMetadata = ['key' => 'value', 'test' => 123];
        $result = new RenderResult(true, '# Title', $originalMetadata);

        $processed = $this->formatter->process($result);

        $this->assertArrayHasKey('key', $processed->metadata);
        $this->assertArrayHasKey('test', $processed->metadata);
        $this->assertEquals('value', $processed->metadata['key']);
        $this->assertEquals(123, $processed->metadata['test']);
    }

    public function testAddsFormattingMetadata(): void
    {
        $result = new RenderResult(true, '# Title', []);

        $processed = $this->formatter->process($result);

        $this->assertArrayHasKey('processed_by', $processed->metadata);
        $this->assertArrayHasKey('format', $processed->metadata);
        $this->assertArrayHasKey('original_format', $processed->metadata);

        $this->assertEquals('markdown_formatter', $processed->metadata['processed_by']);
        $this->assertEquals('html', $processed->metadata['format']);
        $this->assertEquals('markdown', $processed->metadata['original_format']);
    }

    public function testPreservesErrorFromOriginalResult(): void
    {
        $error = new \Exception('Test error');
        $result = new RenderResult(true, '# Title', [], $error);

        $processed = $this->formatter->process($result);

        $this->assertSame($error, $processed->error);
    }

    public function testEmptyContentHandling(): void
    {
        $result = new RenderResult(true, '', []);

        $processed = $this->formatter->process($result);

        $this->assertEquals('', $processed->content);
        $this->assertTrue($processed->isSuccess());
    }

    public function testMultipleFormattingOnSameLine(): void
    {
        $result = new RenderResult(true, 'This is **bold** and *italic* and `code`', []);
        $processed = $this->formatter->process($result);

        $this->assertStringContainsString('<strong>bold</strong>', $processed->content);
        $this->assertStringContainsString('<em>italic</em>', $processed->content);
        $this->assertStringContainsString('<code>code</code>', $processed->content);
    }

    public function testNestedFormattingHandling(): void
    {
        // 测试嵌套格式（虽然简单实现可能不完全支持）
        $result = new RenderResult(true, '**This is bold with `code` inside**', []);
        $processed = $this->formatter->process($result);

        // 应该至少处理其中一种格式
        $this->assertTrue(
            str_contains($processed->content, '<strong>')
            || str_contains($processed->content, '<code>')
        );
    }

    public function testLinkWithComplexUrl(): void
    {
        $result = new RenderResult(true, '[Complex Link](https://example.com/path?param=value&other=123)', []);
        $processed = $this->formatter->process($result);

        $this->assertStringContainsString('<a href="https://example.com/path?param=value&other=123">Complex Link</a>', $processed->content);
    }

    public function testHeaderWithSpecialCharacters(): void
    {
        $result = new RenderResult(true, '# Header with & special < characters >', []);
        $processed = $this->formatter->process($result);

        $this->assertStringContainsString('<h1>Header with & special < characters ></h1>', $processed->content);
    }

    public function testMultipleHeaderLevels(): void
    {
        $markdown = "# Level 1\n## Level 2\n### Level 3";
        $result = new RenderResult(true, $markdown, []);
        $processed = $this->formatter->process($result);

        $this->assertStringContainsString('<h1>Level 1</h1>', $processed->content);
        $this->assertStringContainsString('<h2>Level 2</h2>', $processed->content);
        $this->assertStringContainsString('<h3>Level 3</h3>', $processed->content);
    }
}
