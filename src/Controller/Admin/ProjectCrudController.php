<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\PromptManageBundle\Entity\Project;

/**
 * @extends AbstractCrudController<Project>
 */
#[AdminCrud(routePath: '/prompt-manage/project', routeName: 'prompt_manage_project')]
final class ProjectCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Project::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('项目')
            ->setEntityLabelInPlural('项目管理')
            ->setPageTitle(Crud::PAGE_INDEX, '项目列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建项目')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑项目')
            ->setPageTitle(Crud::PAGE_DETAIL, '项目详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPaginatorPageSize(20)
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
            ->add(TextFilter::new('name', '项目名称'))
            ->add(TextFilter::new('description', '描述'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnIndex()
        ;

        yield TextField::new('name', '项目名称')
            ->setRequired(true)
            ->setMaxLength(50)
            ->setHelp('项目的唯一标识名称，最多50个字符')
        ;

        yield TextareaField::new('description', '项目描述')
            ->setRequired(false)
            ->setMaxLength(255)
            ->setNumOfRows(3)
            ->setHelp('项目的详细描述信息，最多255个字符')
        ;

        // 显示关联的提示词数量
        if (Crud::PAGE_INDEX === $pageName || Crud::PAGE_DETAIL === $pageName) {
            yield IntegerField::new('promptCount', '提示词数量')
                ->setHelp('该项目下的提示词总数')
                ->hideOnForm()
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
    public function createEntity(string $entityFqcn): Project
    {
        return new Project();
    }
}
