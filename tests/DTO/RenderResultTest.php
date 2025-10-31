<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\DTO\RenderResult;

/**
 * @internal
 */
#[CoversClass(RenderResult::class)]
final class RenderResultTest extends TestCase
{
    public function testSuccessfulRenderWithContent(): void
    {
        $content = 'Hello World! This is rendered content.';
        $metadata = ['render_time' => 150, 'engine' => 'twig'];

        $result = new RenderResult(true, $content, $metadata);

        $this->assertTrue($result->success);
        $this->assertSame($content, $result->content);
        $this->assertSame($metadata, $result->metadata);
        $this->assertNull($result->error);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(strlen($content), $result->getContentLength());
    }

    public function testSuccessfulRenderWithEmptyContent(): void
    {
        $result = new RenderResult(true);

        $this->assertTrue($result->success);
        $this->assertSame('', $result->content);
        $this->assertSame([], $result->metadata);
        $this->assertNull($result->error);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(0, $result->getContentLength());
    }

    public function testFailedRenderWithException(): void
    {
        $error = new \RuntimeException('Template compilation failed');
        $metadata = ['error_line' => 5, 'template_name' => 'user_profile.twig'];

        $result = new RenderResult(false, '', $metadata, $error);

        $this->assertFalse($result->success);
        $this->assertSame('', $result->content);
        $this->assertSame($metadata, $result->metadata);
        $this->assertSame($error, $result->error);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(0, $result->getContentLength());
    }

    public function testFailedRenderWithPartialContent(): void
    {
        $partialContent = 'Partial render before error';
        $error = new \Exception('Variable not found');

        $result = new RenderResult(false, $partialContent, [], $error);

        $this->assertFalse($result->success);
        $this->assertSame($partialContent, $result->content);
        $this->assertSame($error, $result->error);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(strlen($partialContent), $result->getContentLength());
    }

    public function testContentLengthCalculation(): void
    {
        $testCases = [
            '' => 0,
            'a' => 1,
            'Hello' => 5,
            'Hello World!' => 12,
            'Multi-line\ncontent\nwith\nnewlines' => 35,
            'Unicode content: café émoji 🎉' => 34, // Note: strlen counts bytes, not characters
        ];

        foreach ($testCases as $content => $expectedLength) {
            $result = new RenderResult(true, $content);
            $this->assertSame($expectedLength, $result->getContentLength());
            $this->assertSame(strlen($content), $result->getContentLength());
        }
    }

    public function testLargeContent(): void
    {
        $largeContent = str_repeat('Lorem ipsum dolor sit amet. ', 1000);
        $result = new RenderResult(true, $largeContent);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(strlen($largeContent), $result->getContentLength());
        $this->assertGreaterThan(10000, $result->getContentLength());
    }

    public function testComplexMetadata(): void
    {
        $metadata = [
            'performance' => [
                'render_time_ms' => 250.5,
                'memory_used_mb' => 1.2,
                'template_cache_hit' => true,
            ],
            'template_info' => [
                'name' => 'newsletter.html.twig',
                'version' => '1.2.3',
                'includes' => ['header.twig', 'footer.twig', 'sidebar.twig'],
            ],
            'context' => [
                'variables_count' => 15,
                'loops_executed' => 3,
                'conditions_evaluated' => 7,
            ],
        ];

        $result = new RenderResult(true, 'Content', $metadata);

        $this->assertSame($metadata, $result->metadata);
        $this->assertSame(250.5, $result->metadata['performance']['render_time_ms']);
        $this->assertTrue($result->metadata['performance']['template_cache_hit']);
        $this->assertCount(3, $result->metadata['template_info']['includes']);
    }

    public function testHtmlContent(): void
    {
        $htmlContent = '<!DOCTYPE html>
<html>
<head>
    <title>Test Page</title>
</head>
<body>
    <h1>Welcome</h1>
    <p>This is a test page with HTML content.</p>
</body>
</html>';

        $result = new RenderResult(true, $htmlContent);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('<!DOCTYPE html>', $result->content);
        $this->assertStringContainsString('<title>Test Page</title>', $result->content);
        $this->assertSame(strlen($htmlContent), $result->getContentLength());
    }

    public function testJsonContent(): void
    {
        $jsonContent = json_encode([
            'status' => 'success',
            'data' => ['id' => 123, 'name' => 'Test'],
            'timestamp' => '2023-01-01T10:00:00Z',
        ]);

        $result = new RenderResult(true, false !== $jsonContent ? $jsonContent : '');

        $this->assertTrue($result->isSuccess());
        $this->assertJson($result->content);
        $this->assertSame(strlen(false !== $jsonContent ? $jsonContent : ''), $result->getContentLength());
    }

    public function testExceptionHandling(): void
    {
        $innerException = new \InvalidArgumentException('Invalid template variable');
        $outerException = new \RuntimeException('Render failed', 500, $innerException);

        $result = new RenderResult(false, 'Partial content', [], $outerException);

        $this->assertSame($outerException, $result->error);
        $this->assertSame($innerException, $result->error->getPrevious());
        $this->assertSame('Render failed', $result->error->getMessage());
        $this->assertSame(500, $result->error->getCode());
    }

    public function testReadonlyProperties(): void
    {
        $result = new RenderResult(true, 'content', ['key' => 'value']);

        // 验证属性是只读的
        $reflection = new \ReflectionClass($result);

        $successProperty = $reflection->getProperty('success');
        $this->assertTrue($successProperty->isReadOnly());

        $contentProperty = $reflection->getProperty('content');
        $this->assertTrue($contentProperty->isReadOnly());

        $metadataProperty = $reflection->getProperty('metadata');
        $this->assertTrue($metadataProperty->isReadOnly());

        $errorProperty = $reflection->getProperty('error');
        $this->assertTrue($errorProperty->isReadOnly());
    }

    public function testSpecialCharacters(): void
    {
        $specialContent = "Content with special chars: \n\t\r\"'\\<>&";
        $result = new RenderResult(true, $specialContent);

        $this->assertSame($specialContent, $result->content);
        $this->assertSame(strlen($specialContent), $result->getContentLength());
        $this->assertTrue($result->isSuccess());
    }

    public function testBinaryContent(): void
    {
        // 模拟二进制内容（虽然实际上是字符串）
        $binaryContent = pack('H*', '48656c6c6f20576f726c6421'); // "Hello World!" in hex
        $result = new RenderResult(true, $binaryContent);

        $this->assertSame($binaryContent, $result->content);
        $this->assertSame(strlen($binaryContent), $result->getContentLength());
        $this->assertSame('Hello World!', $result->content);
    }
}
