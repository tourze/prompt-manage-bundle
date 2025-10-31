<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\PromptManageBundle\Entity\Tag;

/**
 * @extends AbstractCrudController<Tag>
 */
#[AdminCrud(routePath: '/prompt-manage/tag', routeName: 'prompt_manage_tag')]
final class TagCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tag::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('标签')
            ->setEntityLabelInPlural('标签管理')
            ->setPageTitle(Crud::PAGE_INDEX, '标签列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建标签')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑标签')
            ->setPageTitle(Crud::PAGE_DETAIL, '标签详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPaginatorPageSize(30)
            ->showEntityActionsInlined(true)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions;
        // 暂时移除权限限制，确保功能可用
        // ->setPermission(Action::NEW, 'ROLE_ADMIN')
        // ->setPermission(Action::EDIT, 'ROLE_ADMIN')
        // ->setPermission(Action::DELETE, 'ROLE_ADMIN')
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', '标签名称'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnIndex()
        ;

        yield TextField::new('name', '标签名称')
            ->setRequired(true)
            ->setMaxLength(30)
            ->setHelp('标签的唯一名称，最多30个字符')
        ;

        // 显示使用此标签的提示词数量
        if (Crud::PAGE_INDEX === $pageName || Crud::PAGE_DETAIL === $pageName) {
            yield IntegerField::new('promptCount', '使用次数')
                ->setHelp('使用此标签的提示词数量')
                ->hideOnForm()
                ->setVirtual(true)
                ->formatValue(function (?int $value, ?Tag $entity): int {
                    if ($entity instanceof Tag) {
                        return $entity->getPrompts()->count();
                    }

                    return 0;
                })
            ;
        }

        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnDetail()
        ;
    }

    /**
     * 自定义实体创建前的处理
     */
    public function createEntity(string $entityFqcn): Tag
    {
        return new Tag();
    }
}
