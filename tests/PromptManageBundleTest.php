<?php

namespace Tourze\PromptManageBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use Tourze\PromptManageBundle\PromptManageBundle;

/**
 * @internal
 */
#[CoversClass(PromptManageBundle::class)]
#[RunTestsInSeparateProcesses]
class PromptManageBundleTest extends AbstractBundleTestCase
{
    public function testGetBundleDependencies(): void
    {
        $dependencies = PromptManageBundle::getBundleDependencies();

        $this->assertIsArray($dependencies);
        $this->assertArrayHasKey(DoctrineBundle::class, $dependencies);
        $this->assertArrayHasKey(MonologBundle::class, $dependencies);
        $this->assertEquals(['all' => true], $dependencies[DoctrineBundle::class]);
        $this->assertEquals(['all' => true], $dependencies[MonologBundle::class]);
    }
}
