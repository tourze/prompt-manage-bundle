<?php

namespace Tourze\PromptManageBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use Tourze\PromptManageBundle\DependencyInjection\PromptManageExtension;

/**
 * @internal
 */
#[CoversClass(PromptManageExtension::class)]
final class PromptManageExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    protected function createExtension(): PromptManageExtension
    {
        return new PromptManageExtension();
    }

    public function testGetConfigDir(): void
    {
        $extension = $this->createExtension();

        $reflectionClass = new \ReflectionClass($extension);
        $method = $reflectionClass->getMethod('getConfigDir');
        $method->setAccessible(true);

        $configDir = $method->invoke($extension);

        $this->assertIsString($configDir);
        $this->assertStringContainsString('Resources/config', $configDir);
    }
}
