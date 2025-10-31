<?php

namespace Tourze\PromptManageBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class PromptManageExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
