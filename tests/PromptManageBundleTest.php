<?php

namespace Tourze\PromptManageBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use Tourze\PromptManageBundle\PromptManageBundle;

/**
 * @internal
 */
#[CoversClass(PromptManageBundle::class)]
#[RunTestsInSeparateProcesses]
class PromptManageBundleTest extends AbstractBundleTestCase
{
}
