<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\PromptVersion;

/**
 * PromptVersion实体测试
 * @internal
 */
#[CoversClass(PromptVersion::class)]
final class PromptVersionTest extends AbstractEntityTestCase
{
    public function testConstruct(): void
    {
        $version = new PromptVersion();

        self::assertNull($version->getId());
        self::assertNull($version->getPrompt());
        self::assertSame(1, $version->getVersion());
        self::assertSame('', $version->getContent());
        self::assertNull($version->getChangeNote());
    }

    public function testSettersAndGetters(): void
    {
        $version = new PromptVersion();

        $version->setVersion(5);
        self::assertSame(5, $version->getVersion());

        $version->setContent('Test content with {{param}}');
        self::assertSame('Test content with {{param}}', $version->getContent());

        $version->setChangeNote('Added parameter support');
        self::assertSame('Added parameter support', $version->getChangeNote());

        $version->setChangeNote(null);
        self::assertNull($version->getChangeNote());
    }

    public function testVersionValidation(): void
    {
        $version = new PromptVersion();

        // 测试正版本号
        $version->setVersion(1);
        self::assertSame(1, $version->getVersion());

        $version->setVersion(100);
        self::assertSame(100, $version->getVersion());

        // 根据Assert\Positive约束，版本号应该是正数（>0）
        // 这里只测试有效值，无效值的验证交给Symfony Validator
    }

    public function testContentValidation(): void
    {
        $version = new PromptVersion();

        // 测试各种内容格式
        $contents = [
            'Simple text',
            'Template with {{param}}',
            'Multi-line\ncontent\nwith\nbreaks',
            'Content with special chars: !@#$%^&*()',
            'Unicode content: 中文测试 🎉',
            'JSON-like: {"key": "value"}',
            'Long content: ' . str_repeat('A', 1000),
        ];

        foreach ($contents as $content) {
            $version->setContent($content);
            self::assertSame($content, $version->getContent());
        }
    }

    public function testChangeNoteValidation(): void
    {
        $version = new PromptVersion();

        // 测试长变更说明（在约束内）
        $longNote = str_repeat('A', 255);
        $version->setChangeNote($longNote);
        self::assertSame($longNote, $version->getChangeNote());

        // 测试空字符串
        $version->setChangeNote('');
        self::assertSame('', $version->getChangeNote());

        // 测试null
        $version->setChangeNote(null);
        self::assertNull($version->getChangeNote());
    }

    public function testPromptRelationship(): void
    {
        $version = new PromptVersion();
        $prompt = new Prompt();
        $prompt->setName('Test Prompt');

        $version->setPrompt($prompt);
        self::assertSame($prompt, $version->getPrompt());

        $version->setPrompt(null);
        self::assertNull($version->getPrompt());
    }

    public function testToStringWithPrompt(): void
    {
        $prompt = new Prompt();
        $prompt->setName('Chat Assistant');

        $version = new PromptVersion();
        $version->setVersion(3);
        $version->setPrompt($prompt);

        self::assertSame('v3 - Chat Assistant', (string) $version);
    }

    public function testToStringWithoutPrompt(): void
    {
        $version = new PromptVersion();
        $version->setVersion(2);

        self::assertSame('v2 - ', (string) $version);
    }

    public function testToStringWithNullPromptName(): void
    {
        $prompt = new Prompt();
        // prompt名称为空字符串（默认值）

        $version = new PromptVersion();
        $version->setVersion(1);
        $version->setPrompt($prompt);

        self::assertSame('v1 - ', (string) $version);
    }

    public function testVersionNumberFormatting(): void
    {
        $prompt = new Prompt();
        $prompt->setName('Test');

        $testCases = [
            1 => 'v1 - Test',
            10 => 'v10 - Test',
            100 => 'v100 - Test',
            999 => 'v999 - Test',
        ];

        foreach ($testCases as $versionNumber => $expectedString) {
            $version = new PromptVersion();
            $version->setVersion($versionNumber);
            $version->setPrompt($prompt);

            self::assertSame($expectedString, (string) $version);
        }
    }

    public function testCompleteVersionScenario(): void
    {
        $prompt = new Prompt();
        $prompt->setName('Email Template');

        $version = new PromptVersion();
        $version->setPrompt($prompt);
        $version->setVersion(2);
        $version->setContent('Hello {{name}},\n\nThank you for contacting us.\n\nBest regards,\n{{signature}}');
        $version->setChangeNote('Added signature parameter and improved formatting');

        // 验证所有属性
        self::assertSame($prompt, $version->getPrompt());
        self::assertSame(2, $version->getVersion());
        self::assertStringContainsString('{{name}}', $version->getContent());
        self::assertStringContainsString('{{signature}}', $version->getContent());
        self::assertStringContainsString('Added signature parameter', $version->getChangeNote() ?? '');
        self::assertSame('v2 - Email Template', (string) $version);
    }

    public function testMultipleVersionsForSamePrompt(): void
    {
        $prompt = new Prompt();
        $prompt->setName('API Response Template');

        $versions = [];

        // 创建多个版本
        for ($i = 1; $i <= 5; ++$i) {
            $version = new PromptVersion();
            $version->setPrompt($prompt);
            $version->setVersion($i);
            $version->setContent("Content for version {$i}");
            $version->setChangeNote("Changes in version {$i}");
            $versions[] = $version;
        }

        // 验证每个版本
        foreach ($versions as $index => $version) {
            $versionNumber = $index + 1;
            self::assertSame($prompt, $version->getPrompt());
            self::assertSame($versionNumber, $version->getVersion());
            self::assertSame("Content for version {$versionNumber}", $version->getContent());
            self::assertSame("Changes in version {$versionNumber}", $version->getChangeNote());
            self::assertSame("v{$versionNumber} - API Response Template", (string) $version);
        }
    }

    public function testVersionEvolution(): void
    {
        $prompt = new Prompt();
        $prompt->setName('User Greeting');

        // 版本1：简单问候
        $v1 = new PromptVersion();
        $v1->setPrompt($prompt);
        $v1->setVersion(1);
        $v1->setContent('Hello!');
        $v1->setChangeNote('Initial version');

        // 版本2：添加个性化
        $v2 = new PromptVersion();
        $v2->setPrompt($prompt);
        $v2->setVersion(2);
        $v2->setContent('Hello {{name}}!');
        $v2->setChangeNote('Added personalization');

        // 版本3：添加时间相关问候
        $v3 = new PromptVersion();
        $v3->setPrompt($prompt);
        $v3->setVersion(3);
        $v3->setContent('{{greeting}} {{name}}!');
        $v3->setChangeNote('Added time-based greeting');

        $versions = [$v1, $v2, $v3];

        // 验证版本演进
        self::assertSame('Hello!', $v1->getContent());
        self::assertStringContainsString('{{name}}', $v2->getContent());
        self::assertStringContainsString('{{greeting}}', $v3->getContent());
        self::assertStringContainsString('{{name}}', $v3->getContent());

        // 验证变更说明记录了演进历史
        self::assertSame('Initial version', $v1->getChangeNote());
        self::assertStringContainsString('personalization', $v2->getChangeNote() ?? '');
        self::assertStringContainsString('time-based', $v3->getChangeNote() ?? '');
    }

    public function testEmptyAndWhitespaceContent(): void
    {
        $version = new PromptVersion();

        // 测试空字符串内容
        $version->setContent('');
        self::assertSame('', $version->getContent());

        // 测试只有空白字符的内容
        $version->setContent('   ');
        self::assertSame('   ', $version->getContent());

        // 测试换行符
        $version->setContent("\n\n\n");
        self::assertSame("\n\n\n", $version->getContent());

        // 测试制表符
        $version->setContent("\t\t");
        self::assertSame("\t\t", $version->getContent());
    }

    public function testTemplateVariablePatterns(): void
    {
        $version = new PromptVersion();

        $templateContents = [
            'Simple: {{var}}',
            'Multiple: {{var1}} and {{var2}}',
            'Nested: {{user.name}} {{user.email}}',
            'Complex: {{#if condition}}{{value}}{{/if}}',
            'Mixed: Hello {{name}}, today is {{date|format}}',
            'Escaped: \{{not_a_variable}}',
        ];

        foreach ($templateContents as $content) {
            $version->setContent($content);
            self::assertSame($content, $version->getContent());
        }
    }

    public function testLargeContentHandling(): void
    {
        $version = new PromptVersion();

        // 测试大型内容（模拟长模板）
        $largeContent = "# Large Template\n\n";
        $largeContent .= str_repeat("Line {{param}} with content.\n", 1000);
        $largeContent .= "\n## End of template";

        $version->setContent($largeContent);
        self::assertSame($largeContent, $version->getContent());
        self::assertStringContainsString('# Large Template', $version->getContent());
        self::assertStringContainsString('## End of template', $version->getContent());
        self::assertGreaterThan(20000, strlen($version->getContent()));
    }

    public function testPromptVersionBidirectionalRelationship(): void
    {
        $prompt = new Prompt();
        $prompt->setName('Test Prompt');

        $version = new PromptVersion();
        $version->setVersion(1);
        $version->setContent('Test content');

        // 通过version设置prompt
        $version->setPrompt($prompt);
        self::assertSame($prompt, $version->getPrompt());

        // 通过prompt添加version
        $prompt->addVersion($version);
        self::assertTrue($prompt->getVersions()->contains($version));
        self::assertSame($prompt, $version->getPrompt());
    }

    protected function createEntity(): object
    {
        return new PromptVersion();
    }

    /**
     * @return array<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['version', 1],
            ['content', 'Test prompt content'],
            ['changeNote', 'Test change note'],
        ];
    }
}
