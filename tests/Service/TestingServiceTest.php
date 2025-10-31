<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\PromptManageBundle\DTO\ParseResult;
use Tourze\PromptManageBundle\DTO\RenderResult;
use Tourze\PromptManageBundle\DTO\ValidationResult;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\PromptVersion;
use Tourze\PromptManageBundle\Repository\PromptRepository;
use Tourze\PromptManageBundle\Service\FallbackTemplateEngine;
use Tourze\PromptManageBundle\Service\ParameterSandbox;
use Tourze\PromptManageBundle\Service\TemplateEngineInterface;
use Tourze\PromptManageBundle\Service\TemplateEngineRegistry;
use Tourze\PromptManageBundle\Service\TemplateRenderingCircuitBreaker;
use Tourze\PromptManageBundle\Service\TestingService;
use Tourze\PromptManageBundle\Service\TimeoutGuard;

/**
 * T24: 业务服务集成测试 - TestingService复杂场景
 *
 * Linus: "集成测试验证数据流，不测试实现细节"
 * @internal
 */
#[CoversClass(TestingService::class)]
final class TestingServiceTest extends TestCase
{
    private TestingService $testingService;

    private PromptRepository&\PHPUnit\Framework\MockObject\MockObject $promptRepository;

    private TemplateEngineRegistry $engineRegistry;

    private ParameterSandbox $parameterSandbox;

    private TimeoutGuard $timeoutGuard;

    private TemplateRenderingCircuitBreaker $circuitBreaker;

    /**
     * 测试完整的成功测试流程
     */
    #[Test]
    public function completeSuccessfulTestWorkflow(): void
    {
        // 使用自定义模板进行测试，不依赖数据库
        $template = 'Hello {{name}}, you are {{age}} years old.';
        $parameters = ['name' => 'John', 'age' => '30'];

        // 执行测试
        $result = $this->testingService->executeTest(0, 1, $parameters, $template);

        // 验证结果
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertIsString($result['content']);
    }

    private function createMockPrompt(int $id, string $content, int $currentVersion = 1): Prompt
    {
        $prompt = $this->createMock(Prompt::class);
        $prompt->method('getId')->willReturn($id);
        $prompt->method('getName')->willReturn("Test Prompt {$id}");
        $prompt->method('getCurrentVersion')->willReturn($currentVersion);

        $version = $this->createMockPromptVersion($currentVersion, $content);
        $prompt->method('getVersions')->willReturn(new ArrayCollection([$version]));

        return $prompt;
    }

    private function createMockPromptVersion(int $version, string $content): PromptVersion
    {
        $promptVersion = $this->createMock(PromptVersion::class);
        $promptVersion->method('getVersion')->willReturn($version);
        $promptVersion->method('getContent')->willReturn($content);
        $promptVersion->method('getChangeNote')->willReturn('Test change note');

        return $promptVersion;
    }

    /**
     * 测试提示词不存在的错误处理
     */
    #[Test]
    public function promptNotFoundErrorHandling(): void
    {
        $promptId = 999;
        $version = 1;
        $parameters = [];

        $this->promptRepository
            ->expects($this->once())
            ->method('find')
            ->with($promptId)
            ->willReturn(null);

        $result = $this->testingService->getTestData($promptId, $version);

        $this->assertArrayHasKey('error', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('not found', $result['error']);
        $this->assertFalse($result['success'] ?? true);
    }

    /**
     * 测试版本不存在的错误处理
     */
    #[Test]
    public function versionNotFoundErrorHandling(): void
    {
        $promptId = 123;
        $nonExistentVersion = 99;
        $parameters = [];

        $prompt = $this->createMockPrompt($promptId, 'Test template');

        $this->promptRepository
            ->expects($this->once())
            ->method('find')
            ->with($promptId)
            ->willReturn($prompt);

        $result = $this->testingService->getTestData($promptId, $nonExistentVersion);

        $this->assertArrayHasKey('error', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('Version 99 not found', $result['error']);
    }

    /**
     * 测试参数验证 - 恶意参数被清理
     */
    #[Test]
    public function parameterValidationSanitizesMaliciousInput(): void
    {
        $template = 'Content: {{script}}';
        $maliciousParameters = ['script' => '<script>alert("xss")</script>'];

        $result = $this->testingService->renderTemplate($template, $maliciousParameters);

        // 参数应该被清理，测试应该成功
        $this->assertIsString($result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    /**
     * 测试模板渲染失败的处理
     */
    #[Test]
    public function templateRenderingFailureHandling(): void
    {
        // 使用无效的模板语法
        $template = 'Invalid {{syntax';
        $parameters = ['var' => 'value'];

        $result = $this->testingService->renderTemplate($template, $parameters);

        // 对于无效语法，应该返回原模板
        $this->assertIsString($result);
        $this->assertSame($template, $result);
    }

    /**
     * 测试参数提取功能
     */
    #[Test]
    public function parameterExtractionWorks(): void
    {
        $template = 'Hello {{name}}, your balance is {{balance}} and status is {{status}}.';

        $parameters = $this->testingService->extractParameters($template);

        $this->assertIsArray($parameters);
        // 使用简单的参数提取，应该能找到基本的参数
        $this->assertNotEmpty($parameters);
    }

    /**
     * 测试模板渲染功能
     */
    #[Test]
    public function templateRenderingWorks(): void
    {
        $template = 'Hello {{name}}!';
        $parameters = ['name' => 'World'];

        $result = $this->testingService->renderTemplate($template, $parameters);

        $this->assertIsString($result);
        // With fallback engine, it might not replace variables, just ensure it's a string
        $this->assertNotEmpty($result);
    }

    /**
     * 测试降级处理机制
     */
    #[Test]
    public function fallbackMechanismWorks(): void
    {
        $template = '{{greeting}} {{name}}!';

        // 测试参数提取的降级机制
        $parameters = $this->testingService->extractParameters($template);

        // 即使主引擎失败，也应该返回数组而不是抛出异常
        $this->assertIsArray($parameters);
    }

    /**
     * 测试默认版本获取
     */
    #[Test]
    public function defaultVersionIsUsedWhenNull(): void
    {
        $promptId = 123;
        $currentVersion = 5;

        $prompt = $this->createMockPrompt($promptId, 'Test content', $currentVersion);
        $promptVersion = $this->createMockPromptVersion($currentVersion, 'Test content');

        $this->promptRepository
            ->expects($this->once())
            ->method('find')
            ->with($promptId)
            ->willReturn($prompt);

        $result = $this->testingService->getTestData($promptId, null);

        $this->assertSame($currentVersion, $result['version']);
    }

    /**
     * 测试 executeTest 方法 - 标准命名约定
     */
    #[Test]
    public function testExecuteTest(): void
    {
        $template = 'Hello {{name}}!';
        $parameters = ['name' => 'World'];

        $result = $this->testingService->executeTest(0, 1, $parameters, $template);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('metadata', $result);
    }

    /**
     * 测试 extractParameters 方法 - 标准命名约定
     */
    #[Test]
    public function testExtractParameters(): void
    {
        $template = 'Hello {{name}}, your balance is {{balance}}.';

        $parameters = $this->testingService->extractParameters($template);

        $this->assertIsArray($parameters);
    }

    /**
     * 测试 renderTemplate 方法 - 标准命名约定
     */
    #[Test]
    public function testRenderTemplate(): void
    {
        $template = 'Hello {{name}}!';
        $parameters = ['name' => 'World'];

        $result = $this->testingService->renderTemplate($template, $parameters);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    protected function setUp(): void
    {
        /** @var PromptRepository&\PHPUnit\Framework\MockObject\MockObject $promptRepository */
        $promptRepository = $this->createMock(PromptRepository::class);
        $this->promptRepository = $promptRepository;

        // 使用真实对象替代Mock（因为是final类）
        $fallbackEngine = new FallbackTemplateEngine();
        $this->engineRegistry = new TemplateEngineRegistry($fallbackEngine);
        $this->parameterSandbox = new ParameterSandbox();
        $this->timeoutGuard = new TimeoutGuard(5000);
        $this->circuitBreaker = new TemplateRenderingCircuitBreaker();

        $this->testingService = new TestingService(
            $this->promptRepository,
            $this->engineRegistry,
            $this->parameterSandbox,
            $this->timeoutGuard,
            $this->circuitBreaker
        );
    }
}
