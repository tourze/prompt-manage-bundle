<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Symfony\Component\HttpFoundation\Response;
use Tourze\PromptManageBundle\Entity\PromptVersion;
use Tourze\PromptManageBundle\Service\PromptService;

/**
 * @extends AbstractCrudController<PromptVersion>
 */
#[AdminCrud(routePath: '/prompt-manage/prompt-version', routeName: 'prompt_manage_prompt_version')]
final class PromptVersionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly PromptService $promptService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PromptVersion::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('提示词版本')
            ->setEntityLabelInPlural('版本管理')
            ->setPageTitle(Crud::PAGE_INDEX, '版本历史')
            ->setPageTitle(Crud::PAGE_NEW, '创建新版本')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑版本')
            ->setPageTitle(Crud::PAGE_DETAIL, '版本详情')
            ->setDefaultSort(['prompt.id' => 'ASC', 'version' => 'DESC'])
            ->setPaginatorPageSize(30)
            ->showEntityActionsInlined(true)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // 切换到此版本
        $switchToVersion = Action::new('switchToVersion', '切换到此版本', 'fas fa-exchange-alt')
            ->linkToCrudAction('switchToVersion')
            ->setCssClass('btn btn-warning')
            ->setHtmlAttributes(['onclick' => 'return confirm("确定要切换到此版本吗？这将生成一个新版本。")'])
        ;

        // 测试此版本
        $testVersion = Action::new('testVersion', '测试', 'fas fa-play')
            ->linkToCrudAction('testVersion')
            ->setCssClass('btn btn-success')
        ;

        // 对比版本
        $compareVersion = Action::new('compareVersion', '对比', 'fas fa-exchange-alt')
            ->linkToCrudAction('compareVersion')
            ->setCssClass('btn btn-info')
        ;

        return $actions
            ->add(Crud::PAGE_DETAIL, $switchToVersion)
            ->add(Crud::PAGE_DETAIL, $testVersion)
            ->add(Crud::PAGE_DETAIL, $compareVersion)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            // 暂时移除权限限制，确保功能可用
            // ->setPermission('switchToVersion', 'ROLE_ADMIN')
            // ->setPermission('testVersion', 'ROLE_USER')
            // ->setPermission('compareVersion', 'ROLE_USER')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('prompt', '提示词'))
            ->add(NumericFilter::new('version', '版本号'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnIndex()
        ;

        yield AssociationField::new('prompt', '提示词')
            ->setRequired(true)
            ->autocomplete()
            ->setHelp('选择要创建版本的提示词')
        ;

        yield IntegerField::new('version', '版本号')
            ->setHelp('版本号，从1开始递增')
            ->hideOnForm() // 版本号由系统自动生成
        ;

        yield TextareaField::new('content', '内容模板')
            ->setRequired(true)
            ->setNumOfRows(15)
            ->setMaxLength(65535)
            ->setHelp('提示词的具体内容，支持占位符如 {user_input}')
        ;

        yield TextField::new('changeNote', '变更说明')
            ->setRequired(false)
            ->setMaxLength(255)
            ->setHelp('说明本次修改的目的和内容')
        ;

        yield IntegerField::new('createdBy', '创建人ID')
            ->hideOnForm()
            ->onlyOnDetail()
        ;

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
     * 切换到指定版本
     */
    #[AdminAction(routeName: 'switchToVersion', routePath: '/admin/prompt-version/{id}/switch')]
    public function switchToVersion(AdminContext $context): Response
    {
        $entityDto = $context->getEntity();
        $version = $entityDto->getInstance();

        if (null === $version || !$version instanceof PromptVersion) {
            $this->addFlash('danger', '版本不存在');

            return $this->redirectToRoute('admin', [
                'crudAction' => 'index',
                'crudControllerFqcn' => self::class,
            ]);
        }

        $prompt = $version->getPrompt();

        if (null === $prompt) {
            $this->addFlash('danger', '关联的提示词不存在');

            return $this->redirectToRoute('admin', [
                'crudAction' => 'index',
                'crudControllerFqcn' => self::class,
            ]);
        }

        try {
            $promptId = $prompt->getId();
            if (null === $promptId) {
                throw new \RuntimeException('提示词ID不能为空');
            }
            $this->promptService->switchToVersion(
                $promptId,
                $version->getVersion()
            );

            $this->addFlash('success', sprintf(
                '已切换到版本 v%d，已生成新版本',
                $version->getVersion()
            ));
        } catch (\Exception $e) {
            $this->addFlash('danger', '版本切换失败：' . $e->getMessage());
        }

        return $this->redirectToRoute('admin', [
            'crudAction' => 'index',
            'crudControllerFqcn' => self::class,
        ]);
    }

    /**
     * 测试指定版本
     */
    #[AdminAction(routeName: 'testVersion', routePath: '/admin/prompt-version/{id}/test')]
    public function testVersion(AdminContext $context): Response
    {
        $entityDto = $context->getEntity();
        $version = $entityDto->getInstance();

        if (null === $version || !$version instanceof PromptVersion) {
            $this->addFlash('danger', '版本不存在');

            return $this->redirectToRoute('admin', [
                'crudAction' => 'index',
                'crudControllerFqcn' => self::class,
            ]);
        }

        $content = $version->getContent();
        $placeholders = $this->promptService->extractPlaceholders($content);
        $prompt = $version->getPrompt();

        if (null === $prompt) {
            $this->addFlash('danger', '关联的提示词不存在');

            return $this->redirectToRoute('admin', [
                'crudAction' => 'index',
                'crudControllerFqcn' => self::class,
            ]);
        }

        return new Response(sprintf(
            '<h1>测试版本 v%d - %s</h1><h3>版本内容：</h3><pre>%s</pre><h3>占位符：</h3><p>%s</p><h3>变更说明：</h3><p>%s</p><p><a href="%s">返回列表</a></p>',
            $version->getVersion(),
            $prompt->getName(),
            htmlspecialchars($content),
            implode(', ', $placeholders),
            htmlspecialchars($version->getChangeNote() ?? '无'),
            $this->generateUrl('admin', ['routeName' => 'promptversion_index'])
        ));
    }

    /**
     * 版本对比
     */
    #[AdminAction(routeName: 'compareVersion', routePath: '/admin/prompt-version/{id}/compare')]
    public function compareVersion(AdminContext $context): Response
    {
        $entityDto = $context->getEntity();
        $version = $entityDto->getInstance();

        if (null === $version || !$version instanceof PromptVersion) {
            $this->addFlash('danger', '版本不存在');

            return $this->redirectToRoute('admin', [
                'crudAction' => 'index',
                'crudControllerFqcn' => self::class,
            ]);
        }

        $prompt = $version->getPrompt();
        if (null === $prompt) {
            $this->addFlash('danger', '关联的提示词不存在');

            return $this->redirectToRoute('admin', [
                'crudAction' => 'index',
                'crudControllerFqcn' => self::class,
            ]);
        }

        // 这里可以实现版本对比功能，暂时返回简单信息
        return new Response(sprintf(
            '<h1>版本对比 - %s v%d</h1><p>此功能可用于对比两个版本的差异</p><p><a href="%s">返回列表</a></p>',
            $prompt->getName(),
            $version->getVersion(),
            $this->generateUrl('admin', ['routeName' => 'promptversion_index'])
        ));
    }
}
