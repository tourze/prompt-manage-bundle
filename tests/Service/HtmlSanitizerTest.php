<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\DTO\RenderResult;
use Tourze\PromptManageBundle\Service\HtmlSanitizer;

/**
 * @internal
 */
#[CoversClass(HtmlSanitizer::class)]
final class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new HtmlSanitizer();
    }

    public function testGetName(): void
    {
        $this->assertEquals('html_sanitizer', $this->sanitizer->getName());
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(100, $this->sanitizer->getPriority());
    }

    public function testSupportsSuccessfulResult(): void
    {
        $result = new RenderResult(true, 'content', []);
        $this->assertTrue($this->sanitizer->supports($result));
    }

    public function testDoesNotSupportFailedResult(): void
    {
        $result = new RenderResult(false, 'content', []);
        $this->assertFalse($this->sanitizer->supports($result));
    }

    public function testProcessFailedResultReturnsUnchanged(): void
    {
        $result = new RenderResult(false, 'content', []);
        $processed = $this->sanitizer->process($result);

        $this->assertSame($result, $processed);
    }

    public function testRemovesScriptTags(): void
    {
        $content = '<p>Hello</p><script>alert("xss")</script><p>World</p>';
        $result = new RenderResult(true, $content, []);

        $processed = $this->sanitizer->process($result);

        $this->assertStringNotContainsString('<script>', $processed->content);
        $this->assertStringNotContainsString('alert', $processed->content);
        $this->assertStringContainsString('&lt;p&gt;Hello&lt;/p&gt;', $processed->content);
        $this->assertStringContainsString('&lt;p&gt;World&lt;/p&gt;', $processed->content);
    }

    public function testRemovesIframeTags(): void
    {
        $content = '<div>Content</div><iframe src="malicious.html"></iframe>';
        $result = new RenderResult(true, $content, []);

        $processed = $this->sanitizer->process($result);

        $this->assertStringNotContainsString('<iframe>', $processed->content);
        $this->assertStringNotContainsString('malicious.html', $processed->content);
    }

    public function testRemovesEventHandlers(): void
    {
        $content = '<button onclick="malicious()">Click</button>';
        $result = new RenderResult(true, $content, []);

        $processed = $this->sanitizer->process($result);

        $this->assertStringNotContainsString('onclick', $processed->content);
        $this->assertStringNotContainsString('malicious()', $processed->content);
    }

    public function testRemovesJavaScriptUrls(): void
    {
        $content = '<a href="javascript:alert(\'xss\')">Link</a>';
        $result = new RenderResult(true, $content, []);

        $processed = $this->sanitizer->process($result);

        $this->assertStringNotContainsString('javascript:', $processed->content);
        // After javascript: is removed, the content is HTML-encoded, so "alert" becomes part of the attribute value
        // We verify that the dangerous protocol is removed, which is the main security concern
        $this->assertStringContainsString('&lt;a href=&quot;alert(', $processed->content);
    }

    public function testHtmlEntityEncoding(): void
    {
        $content = '<div class="test">Hello & goodbye</div>';
        $result = new RenderResult(true, $content, []);

        $processed = $this->sanitizer->process($result);

        $this->assertStringContainsString('&lt;div', $processed->content);
        $this->assertStringContainsString('&gt;', $processed->content);
        $this->assertStringContainsString('&amp;', $processed->content);
        $this->assertStringContainsString('&quot;', $processed->content);
    }

    public function testComplexMaliciousContent(): void
    {
        $content = '<script>var x=1;</script><iframe src="bad.com"></iframe><div onclick="hack()" onmouseover="steal()">Text</div>';
        $result = new RenderResult(true, $content, []);

        $processed = $this->sanitizer->process($result);

        // 验证所有危险元素都被移除
        $this->assertStringNotContainsString('<script>', $processed->content);
        $this->assertStringNotContainsString('<iframe>', $processed->content);
        $this->assertStringNotContainsString('onclick', $processed->content);
        $this->assertStringNotContainsString('onmouseover', $processed->content);
        $this->assertStringNotContainsString('hack()', $processed->content);
        $this->assertStringNotContainsString('steal()', $processed->content);
        $this->assertStringNotContainsString('bad.com', $processed->content);
    }

    public function testPreservesMetadata(): void
    {
        $originalMetadata = ['key' => 'value', 'test' => 123];
        $content = '<p>Test content</p>';
        $result = new RenderResult(true, $content, $originalMetadata);

        $processed = $this->sanitizer->process($result);

        $this->assertArrayHasKey('key', $processed->metadata);
        $this->assertArrayHasKey('test', $processed->metadata);
        $this->assertEquals('value', $processed->metadata['key']);
        $this->assertEquals(123, $processed->metadata['test']);
    }

    public function testAddsProcessingMetadata(): void
    {
        $content = '<p>Test content</p>';
        $result = new RenderResult(true, $content, []);

        $processed = $this->sanitizer->process($result);

        $this->assertArrayHasKey('processed_by', $processed->metadata);
        $this->assertArrayHasKey('original_length', $processed->metadata);
        $this->assertArrayHasKey('sanitized_length', $processed->metadata);

        $this->assertEquals('html_sanitizer', $processed->metadata['processed_by']);
        $this->assertEquals(strlen($content), $processed->metadata['original_length']);
        $this->assertEquals(strlen($processed->content), $processed->metadata['sanitized_length']);
    }

    public function testPreservesErrorFromOriginalResult(): void
    {
        $error = new \Exception('Test error');
        $result = new RenderResult(true, 'content', [], $error);

        $processed = $this->sanitizer->process($result);

        $this->assertSame($error, $processed->error);
    }

    public function testEmptyContentHandling(): void
    {
        $result = new RenderResult(true, '', []);

        $processed = $this->sanitizer->process($result);

        $this->assertEquals('', $processed->content);
        $this->assertTrue($processed->isSuccess());
        $this->assertEquals(0, $processed->metadata['original_length']);
        $this->assertEquals(0, $processed->metadata['sanitized_length']);
    }

    public function testSpecialCharactersEncoding(): void
    {
        $content = '"Hello" & <goodbye> \' + "world"';
        $result = new RenderResult(true, $content, []);

        $processed = $this->sanitizer->process($result);

        $this->assertStringContainsString('&quot;Hello&quot;', $processed->content);
        $this->assertStringContainsString('&amp;', $processed->content);
        $this->assertStringContainsString('&lt;goodbye&gt;', $processed->content);
        // PHP 8.4+ uses &apos; instead of &#039;
        $this->assertMatchesRegularExpression('/(&apos;|&#039;)/', $processed->content);
    }

    public function testCaseInsensitiveScriptTagRemoval(): void
    {
        $content = '<SCRIPT>alert(1)</SCRIPT><Script>alert(2)</Script><script>alert(3)</script>';
        $result = new RenderResult(true, $content, []);

        $processed = $this->sanitizer->process($result);

        $this->assertStringNotContainsString('SCRIPT', $processed->content);
        $this->assertStringNotContainsString('Script', $processed->content);
        $this->assertStringNotContainsString('script', $processed->content);
        $this->assertStringNotContainsString('alert', $processed->content);
    }

    public function testCaseInsensitiveEventHandlerRemoval(): void
    {
        $content = '<div ONCLICK="bad()" onMouseOver="evil()">Content</div>';
        $result = new RenderResult(true, $content, []);

        $processed = $this->sanitizer->process($result);

        $this->assertStringNotContainsString('ONCLICK', $processed->content);
        $this->assertStringNotContainsString('onMouseOver', $processed->content);
        $this->assertStringNotContainsString('bad()', $processed->content);
        $this->assertStringNotContainsString('evil()', $processed->content);
    }

    public function testCaseInsensitiveJavaScriptUrlRemoval(): void
    {
        $content = '<a href="JAVASCRIPT:alert(1)">Link1</a><a href="JavaScript:alert(2)">Link2</a>';
        $result = new RenderResult(true, $content, []);

        $processed = $this->sanitizer->process($result);

        $this->assertStringNotContainsString('JAVASCRIPT:', $processed->content);
        $this->assertStringNotContainsString('JavaScript:', $processed->content);
        // After javascript: is removed, the content is HTML-encoded, so "alert" becomes part of the attribute value
        // We verify that the dangerous protocol is removed, which is the main security concern
        $this->assertStringContainsString('&lt;a href=&quot;alert(1)&quot;&gt;', $processed->content);
        $this->assertStringContainsString('&lt;a href=&quot;alert(2)&quot;&gt;', $processed->content);
    }
}
