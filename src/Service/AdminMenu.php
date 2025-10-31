<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\PromptManageBundle\Entity\Project;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\PromptVersion;
use Tourze\PromptManageBundle\Entity\Tag;

#[Autoconfigure(public: true)]
#[AutoconfigureTag(name: 'easy-admin-menu.provider')]
readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        // 创建或获取AI提示词管理菜单分组
        if (null === $item->getChild('AI 提示词管理')) {
            $item->addChild('AI 提示词管理');
        }

        $promptMenu = $item->getChild('AI 提示词管理');

        if (null === $promptMenu) {
            return;
        }

        // 添加提示词管理菜单项
        $promptMenu->addChild('提示词管理')
            ->setUri($this->linkGenerator->getCurdListPage(Prompt::class))
            ->setAttribute('icon', 'fas fa-comment-dots')
            ->setAttribute('description', '管理AI提示词模板')
        ;

        // 添加版本历史菜单项
        $promptMenu->addChild('版本历史')
            ->setUri($this->linkGenerator->getCurdListPage(PromptVersion::class))
            ->setAttribute('icon', 'fas fa-code-branch')
            ->setAttribute('description', '查看提示词版本历史')
        ;

        // 添加项目管理菜单项
        $promptMenu->addChild('项目管理')
            ->setUri($this->linkGenerator->getCurdListPage(Project::class))
            ->setAttribute('icon', 'fas fa-folder')
            ->setAttribute('description', '管理提示词项目分组')
        ;

        // 添加标签管理菜单项
        $promptMenu->addChild('标签管理')
            ->setUri($this->linkGenerator->getCurdListPage(Tag::class))
            ->setAttribute('icon', 'fas fa-tags')
            ->setAttribute('description', '管理提示词标签')
        ;
    }
}
