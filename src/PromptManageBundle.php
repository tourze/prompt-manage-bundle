<?php

namespace Tourze\PromptManageBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DoctrineIndexedBundle\DoctrineIndexedBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\EasyAdminMenuBundle\EasyAdminMenuBundle;

class PromptManageBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            EasyAdminBundle::class => ['all' => true],
            MonologBundle::class => ['all' => true],
            TwigBundle::class => ['all' => true],
            EasyAdminMenuBundle::class => ['all' => true],
            DoctrineIndexedBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
        ];
    }
}
