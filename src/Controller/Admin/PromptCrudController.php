<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Service\PromptService;

/**
 * @extends AbstractCrudController<Prompt>
 */
#[AdminCrud(routePath: '/prompt-manage/prompt', routeName: 'prompt_manage_prompt')]
final class PromptCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly PromptService $promptService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Prompt::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('提示词')
            ->setEntityLabelInPlural('提示词管理')
            ->setPageTitle(Crud::PAGE_INDEX, '提示词列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建提示词')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑提示词')
            ->setPageTitle(Crud::PAGE_DETAIL, '提示词详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->showEntityActionsInlined(true)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // 版本管理操作
        $manageVersions = Action::new('manageVersions', '版本管理', 'fas fa-code-branch')
            ->linkToCrudAction('manageVersions')
            ->setCssClass('btn btn-info')
        ;

        // 测试提示词操作
        $testPrompt = Action::new('testPrompt', '测试', 'fas fa-play')
            ->linkToCrudAction('testPrompt')
            ->setCssClass('btn btn-success')
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, $manageVersions)
            ->add(Crud::PAGE_INDEX, $testPrompt)
            ->add(Crud::PAGE_DETAIL, $manageVersions)
            ->add(Crud::PAGE_DETAIL, $testPrompt)
            // 暂时移除权限限制，确保功能可用
            // ->setPermission(Action::NEW, 'ROLE_ADMIN')
            // ->setPermission(Action::EDIT, 'ROLE_ADMIN')
            // ->setPermission('softDelete', 'ROLE_ADMIN')
            // ->setPermission('manageVersions', 'ROLE_USER')
            // ->setPermission('testPrompt', 'ROLE_USER')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', '名称'))
            ->add(EntityFilter::new('project', '项目'))
            ->add(EntityFilter::new('tags', '标签'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        foreach ($this->getBasicFields() as $field) {
            yield $field;
        }
        foreach ($this->getFormFields($pageName) as $field) {
            yield $field;
        }
        foreach ($this->getVersionFields() as $field) {
            yield $field;
        }
        foreach ($this->getContentPreviewFields($pageName) as $field) {
            yield $field;
        }
        foreach ($this->getMetadataFields() as $field) {
            yield $field;
        }
    }

    /**
     * 获取基础字段配置
     * @return \Generator<int, FieldInterface|string, mixed, void>
     */
    private function getBasicFields(): \Generator
    {
        yield IdField::new('id', 'ID')->onlyOnIndex();

        yield TextField::new('name', '名称')
            ->setRequired(true)
            ->setMaxLength(100)
            ->setHelp('提示词的唯一标识名称，最多100个字符')
        ;

        yield AssociationField::new('project', '项目')
            ->setRequired(false)
            ->autocomplete()
            ->setHelp('选择提示词所属项目，可留空')
        ;

        yield AssociationField::new('tags', '标签')
            ->setRequired(false)
            ->autocomplete()
            ->setHelp('为提示词添加标签，支持多选')
        ;
    }

    /**
     * 获取表单特定字段
     * @return \Generator<int, FieldInterface|string, mixed, void>
     */
    private function getFormFields(string $pageName): \Generator
    {
        if (Crud::PAGE_NEW !== $pageName && Crud::PAGE_EDIT !== $pageName) {
            return;
        }

        yield TextareaField::new('content', '提示词内容')
            ->setRequired(true)
            ->setNumOfRows(15)
            ->setHelp('提示词的具体内容，支持占位符如 {user_input}。编辑时会创建新版本。')
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('data', $this->getCurrentVersionContent($pageName))
        ;

        yield TextField::new('changeNote', '变更说明')
            ->setRequired(Crud::PAGE_EDIT === $pageName)
            ->setMaxLength(255)
            ->setHelp(Crud::PAGE_EDIT === $pageName
                ? '说明本次修改的目的和内容（必填）'
                : '说明创建的目的和内容（可选）')
            ->setFormTypeOption('mapped', false)
        ;
    }

    /**
     * 获取版本相关字段
     * @return \Generator<int, FieldInterface|string, mixed, void>
     */
    private function getVersionFields(): \Generator
    {
        yield IntegerField::new('currentVersion', '当前版本')
            ->setHelp('当前激活的版本号')
            ->hideOnForm()
        ;
    }

    /**
     * 获取内容预览字段
     * @return \Generator<int, FieldInterface|string, mixed, void>
     */
    private function getContentPreviewFields(string $pageName): \Generator
    {
        if (Crud::PAGE_DETAIL !== $pageName && Crud::PAGE_INDEX !== $pageName) {
            return;
        }

        yield TextareaField::new('currentVersionContent', '内容预览')
            ->setNumOfRows(3)
            ->setHelp('当前版本的内容预览')
            ->hideOnForm()
            ->formatValue($this->getContentFormatter())
        ;
    }

    /**
     * 获取元数据字段
     * @return \Generator<int, FieldInterface|string, mixed, void>
     */
    private function getMetadataFields(): \Generator
    {
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
     * 获取内容格式化器
     * @return callable(mixed): string
     */
    private function getContentFormatter(): callable
    {
        return function ($value): string {
            if (!is_string($value)) {
                if (null === $value) {
                    return '';
                }
                if (is_scalar($value)) {
                    return (string) $value;
                }

                return '[非字符串类型]';
            }

            if (strlen($value) > 200) {
                return substr($value, 0, 200) . '...';
            }

            return $value;
        };
    }

    /**
     * 版本管理页面
     */
    #[AdminAction(routeName: 'manage_versions', routePath: '/manage-versions')]
    public function manageVersions(AdminContext $context): Response
    {
        $entity = $context->getEntity()->getInstance();

        if (!$entity instanceof Prompt) {
            throw new \InvalidArgumentException('提示词实体不存在或类型错误');
        }

        // 获取该提示词的所有版本
        $promptId = $entity->getId();
        if (null === $promptId) {
            throw new \InvalidArgumentException('提示词ID不能为空');
        }

        $versions = $this->promptService->getPromptVersions($promptId);

        // 生成返回列表的URL
        $backUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction('index')
            ->generateUrl()
        ;

        // 构建版本管理页面HTML
        $versionsHtml = '';
        foreach ($versions as $version) {
            $versionsHtml .= sprintf(
                '<div style="border: 1px solid #ddd; padding: 10px; margin: 5px 0;"><strong>v%d</strong> - %s<br><small>%s</small><br><textarea readonly style="width:100%%; height:60px;">%s</textarea></div>',
                $version->getVersion(),
                $version->getChangeNote(),
                $version->getCreateTime()?->format('Y-m-d H:i:s'),
                htmlspecialchars($version->getContent())
            );
        }

        return new Response(sprintf(
            '<html><head><title>版本管理 - %s</title></head><body>
            <h1>版本管理 - %s</h1>
            <p>当前版本：v%d</p>
            <div>%s</div>
            <p><a href="%s" style="padding: 8px 16px; background: #007cba; color: white; text-decoration: none; border-radius: 4px;">返回列表</a></p>
            </body></html>',
            htmlspecialchars($entity->getName()),
            htmlspecialchars($entity->getName()),
            $entity->getCurrentVersion(),
            $versionsHtml,
            $backUrl
        ));
    }

    /**
     * 测试提示词页面
     */
    #[AdminAction(routeName: 'test_prompt', routePath: '/test-prompt')]
    public function testPrompt(AdminContext $context): Response
    {
        $prompt = $context->getEntity()->getInstance();

        if (null === $prompt || !$prompt instanceof Prompt) {
            throw new \InvalidArgumentException('提示词不存在');
        }

        $content = $prompt->getCurrentVersionContent() ?? '';
        $placeholders = $this->promptService->extractPlaceholders($content);

        // 生成返回列表的URL
        $backUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction('index')
            ->generateUrl()
        ;

        // 返回测试页面
        return new Response(sprintf(
            '<html><head><title>测试提示词 - %s</title></head><body>
            <h1>测试提示词 - %s</h1>
            <h3>当前内容：</h3>
            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px;">%s</pre>
            <h3>占位符：</h3>
            <p><strong>%s</strong></p>
            <p><a href="%s" style="padding: 8px 16px; background: #007cba; color: white; text-decoration: none; border-radius: 4px;">返回列表</a></p>
            </body></html>',
            htmlspecialchars($prompt->getName()),
            htmlspecialchars($prompt->getName()),
            htmlspecialchars($content),
            implode(', ', $placeholders),
            $backUrl
        ));
    }

    /**
     * 处理创建提示词的逻辑
     * @param mixed $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Prompt) {
            if (!is_object($entityInstance)) {
                throw new \InvalidArgumentException('Entity instance must be an object');
            }
            $this->handleNonPromptEntity($entityManager, $entityInstance);

            return;
        }

        [$content, $changeNote] = $this->extractFormData();
        $this->validateContent($content);

        $this->createPromptWithVersion($entityInstance, $content, $changeNote);
    }

    /**
     * 处理编辑提示词的逻辑
     * @param mixed $entityInstance
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Prompt) {
            if (!is_object($entityInstance)) {
                throw new \InvalidArgumentException('Entity instance must be an object');
            }
            $this->handleNonPromptEntity($entityManager, $entityInstance);

            return;
        }

        [$content, $changeNote] = $this->extractFormData();

        if ('' === trim($content)) {
            parent::updateEntity($entityManager, $entityInstance);

            return;
        }

        $this->validateUpdateData($changeNote);
        $this->updatePromptWithVersion($entityManager, $entityInstance, $content, $changeNote);
    }

    /**
     * 获取当前版本内容用于表单预填充
     */
    private function getCurrentVersionContent(string $pageName): ?string
    {
        if (Crud::PAGE_EDIT !== $pageName) {
            return null;
        }

        $context = $this->getContext();
        if (null === $context) {
            return null;
        }

        $entity = $context->getEntity()->getInstance();
        if (!$entity instanceof Prompt) {
            return null;
        }

        return $entity->getCurrentVersionContent();
    }

    /**
     * 处理非 Prompt 实体
     */
    private function handleNonPromptEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        /** @var Prompt $entityInstance */
        parent::persistEntity($entityManager, $entityInstance);
    }

    /**
     * 提取表单数据
     * @return array{string, string}
     */
    private function extractFormData(): array
    {
        $context = $this->getContext();
        if (null === $context) {
            throw new \RuntimeException('AdminContext 不能为空');
        }

        $formData = $context->getRequest()->request->all();
        $promptData = $formData['Prompt'] ?? [];
        if (!is_array($promptData)) {
            $promptData = [];
        }

        $contentRaw = $promptData['content'] ?? '';
        $changeNoteRaw = $promptData['changeNote'] ?? '初始版本';

        $content = is_string($contentRaw) ? $contentRaw : '';
        $changeNote = is_string($changeNoteRaw) ? $changeNoteRaw : '初始版本';

        return [$content, $changeNote];
    }

    /**
     * 验证内容不为空
     */
    private function validateContent(string $content): void
    {
        if ('' === trim($content)) {
            throw new \InvalidArgumentException('提示词内容不能为空');
        }
    }

    /**
     * 验证更新数据
     */
    private function validateUpdateData(string $changeNote): void
    {
        if ('' === trim($changeNote)) {
            throw new \InvalidArgumentException('编辑提示词时必须提供变更说明');
        }
    }

    /**
     * 创建提示词及其版本
     */
    private function createPromptWithVersion(Prompt $entityInstance, string $content, string $changeNote): void
    {
        try {
            $projectName = $entityInstance->getProject()?->getName();
            $tagNames = array_map(
                static fn ($tag) => $tag->getName(),
                iterator_to_array($entityInstance->getTags())
            );

            $createdPrompt = $this->promptService->createPrompt(
                $entityInstance->getName(),
                $content,
                $projectName,
                $tagNames,
                null,
                $changeNote
            );

            $this->addFlash('success', sprintf('提示词 "%s" 创建成功，初始版本为 v1', $createdPrompt->getName()));
        } catch (\Exception $e) {
            $this->addFlash('danger', '创建失败：' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 更新提示词及其版本
     */
    private function updatePromptWithVersion(EntityManagerInterface $entityManager, Prompt $entityInstance, string $content, string $changeNote): void
    {
        try {
            $promptId = $entityInstance->getId();
            if (null === $promptId) {
                throw new \RuntimeException('提示词ID不能为空');
            }

            parent::updateEntity($entityManager, $entityInstance);
            $this->promptService->addVersion($promptId, $content, $changeNote);

            $this->addFlash('success', sprintf(
                '提示词 "%s" 更新成功，创建新版本',
                $entityInstance->getName()
            ));
        } catch (\Exception $e) {
            $this->addFlash('danger', '更新失败：' . $e->getMessage());
            throw $e;
        }
    }
}
