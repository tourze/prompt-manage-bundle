<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;
use Tourze\PromptManageBundle\Entity\Project;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\PromptVersion;
use Tourze\PromptManageBundle\Entity\Tag;
use Tourze\PromptManageBundle\Service\AdminMenu;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private LinkGeneratorInterface&MockObject $linkGenerator;

    private AdminMenu $adminMenu;

    protected function onSetUp(): void
    {
        $this->linkGenerator = $this->createMock(LinkGeneratorInterface::class);

        // 将Mock的LinkGenerator注入到容器中
        self::getContainer()->set(LinkGeneratorInterface::class, $this->linkGenerator);

        // 现在获取AdminMenu服务，它将使用我们的Mock LinkGenerator
        $this->adminMenu = self::getService(AdminMenu::class);
    }

    public function testInvokeCreatesPromptManagementMenuWhenNotExists(): void
    {
        $rootItem = $this->createMock(ItemInterface::class);
        $promptMenu = $this->createMock(ItemInterface::class);

        // 创建新的子菜单
        $rootItem->expects($this->once())
            ->method('addChild')
            ->with('AI 提示词管理')
            ->willReturn($promptMenu)
        ;

        // 第一次调用getChild返回null，第二次返回promptMenu
        $getChildCallCount = 0;
        $rootItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('AI 提示词管理')
            ->willReturnCallback(function () use (&$getChildCallCount, $promptMenu) {
                ++$getChildCallCount;

                return 1 === $getChildCallCount ? null : $promptMenu;
            })
        ;

        $this->setupMenuItemExpectations($promptMenu);
        $this->setupLinkGeneratorExpectations();

        ($this->adminMenu)($rootItem);
    }

    public function testInvokeUsesExistingPromptManagementMenu(): void
    {
        $rootItem = $this->createMock(ItemInterface::class);
        $promptMenu = $this->createMock(ItemInterface::class);

        // 根菜单项已经有"AI 提示词管理"子菜单
        // 注意：getChild会被调用两次，第一次检查是否存在，第二次获取菜单
        $rootItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('AI 提示词管理')
            ->willReturn($promptMenu)
        ;

        // 不应该创建新的子菜单
        $rootItem->expects($this->never())
            ->method('addChild')
        ;

        $this->setupMenuItemExpectations($promptMenu);
        $this->setupLinkGeneratorExpectations();

        ($this->adminMenu)($rootItem);
    }

    public function testInvokeReturnsEarlyWhenPromptMenuIsNull(): void
    {
        $rootItem = $this->createMock(ItemInterface::class);

        // 模拟创建菜单后返回null的情况
        $getChildCallCount = 0;
        $rootItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('AI 提示词管理')
            ->willReturnCallback(function () use (&$getChildCallCount) {
                ++$getChildCallCount;

                return null; // 总是返回null
            })
        ;

        $rootItem->expects($this->once())
            ->method('addChild')
            ->with('AI 提示词管理')
            ->willReturn($this->createMock(ItemInterface::class))
        ;

        // LinkGenerator不应该被调用
        $this->linkGenerator->expects($this->never())
            ->method('getCurdListPage')
        ;

        ($this->adminMenu)($rootItem);
    }

    public function testInvokeAddsAllExpectedMenuItems(): void
    {
        $rootItem = $this->createMock(ItemInterface::class);
        $promptMenu = $this->createMock(ItemInterface::class);

        // getChild会被调用两次
        $rootItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('AI 提示词管理')
            ->willReturn($promptMenu)
        ;

        // 由于菜单已存在，不会调用addChild
        $rootItem->expects($this->never())
            ->method('addChild')
        ;

        $this->setupMenuItemExpectations($promptMenu);
        $this->setupLinkGeneratorExpectations();

        ($this->adminMenu)($rootItem);
    }

    private function setupMenuItemExpectations(ItemInterface&MockObject $promptMenu): void
    {
        // 设置每个菜单项的期望
        $menuItems = [
            ['提示词管理', 'fas fa-comment-dots', '管理AI提示词模板'],
            ['版本历史', 'fas fa-code-branch', '查看提示词版本历史'],
            ['项目管理', 'fas fa-folder', '管理提示词项目分组'],
            ['标签管理', 'fas fa-tags', '管理提示词标签'],
        ];

        $childItems = [];
        foreach ($menuItems as $i => $item) {
            $childItem = $this->createMock(ItemInterface::class);
            $childItem->expects($this->once())
                ->method('setUri')
                ->with(self::callback(function ($value) {
                    return is_string($value);
                }))
                ->willReturnSelf()
            ;

            $attributeCallCount = 0;
            $childItem->expects($this->exactly(2))
                ->method('setAttribute')
                ->willReturnCallback(function ($key, $value) use ($item, &$attributeCallCount, $childItem) {
                    ++$attributeCallCount;
                    if (1 === $attributeCallCount) {
                        $this->assertEquals('icon', $key);
                        $this->assertEquals($item[1], $value);
                    } elseif (2 === $attributeCallCount) {
                        $this->assertEquals('description', $key);
                        $this->assertEquals($item[2], $value);
                    }

                    return $childItem; // 返回自身以支持链式调用
                })
            ;

            $childItems[] = $childItem;
        }

        $addChildCallCount = 0;
        $menuTitles = ['提示词管理', '版本历史', '项目管理', '标签管理'];
        $promptMenu->expects($this->exactly(4))
            ->method('addChild')
            ->willReturnCallback(function ($title) use (&$addChildCallCount, $menuTitles, $childItems) {
                $this->assertEquals($menuTitles[$addChildCallCount], $title);

                return $childItems[$addChildCallCount++];
            })
        ;
    }

    private function setupLinkGeneratorExpectations(): void
    {
        $linkCallCount = 0;
        $entityClasses = [Prompt::class, PromptVersion::class, Project::class, Tag::class];
        $urls = ['/admin/prompt-manage/prompt', '/admin/prompt-manage/prompt-version', '/admin/prompt-manage/project', '/admin/prompt-manage/tag'];

        $this->linkGenerator->expects($this->exactly(4))
            ->method('getCurdListPage')
            ->willReturnCallback(function ($entityClass) use (&$linkCallCount, $entityClasses, $urls) {
                $this->assertEquals($entityClasses[$linkCallCount], $entityClass);

                return $urls[$linkCallCount++];
            })
        ;
    }
}
