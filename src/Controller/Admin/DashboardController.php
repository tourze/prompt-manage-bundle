<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\PromptManageBundle\Entity\Project;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\PromptVersion;
use Tourze\PromptManageBundle\Entity\Tag;

final class DashboardController extends AbstractDashboardController
{
    #[Route(path: '/admin/prompt-manage', name: 'prompt_manage_dashboard')]
    public function __invoke(): Response
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator
            ->setController(ProjectCrudController::class)
            ->generateUrl()
        );
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('提示词管理系统')
            ->setFaviconPath('favicon.ico')
        ;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('仪表板', 'fa fa-home');

        yield MenuItem::section('项目管理');
        yield MenuItem::linkToCrud('项目', 'fa fa-folder', Project::class);

        yield MenuItem::section('提示词管理');
        yield MenuItem::linkToCrud('提示词', 'fa fa-file-text', Prompt::class);
        yield MenuItem::linkToCrud('版本', 'fa fa-code-branch', PromptVersion::class);

        yield MenuItem::section('分类管理');
        yield MenuItem::linkToCrud('标签', 'fa fa-tags', Tag::class);
    }
}
