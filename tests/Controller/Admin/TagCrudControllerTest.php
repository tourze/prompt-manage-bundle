<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DomCrawler\Crawler;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\PromptManageBundle\Controller\Admin\TagCrudController;
use Tourze\PromptManageBundle\Entity\Tag;

/**
 * @internal
 */
#[CoversClass(TagCrudController::class)]
#[RunTestsInSeparateProcesses]
final class TagCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'name' => ['name'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'name' => ['name'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideDetailPageFields(): iterable
    {
        yield 'name' => ['name'];
        yield 'promptCount' => ['promptCount'];
        yield 'createTime' => ['createTime'];
        yield 'updateTime' => ['updateTime'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '标签名称' => ['标签名称'];
        yield '使用次数' => ['使用次数'];
        yield '创建时间' => ['创建时间'];
    }

    #[DataProvider('provideDetailPageExpectations')]
    public function testDetailPageShowsConfiguredFields(string $selector, string $expectedKey, bool $negate = false): void
    {
        $client = $this->createAuthenticatedClient();
        $tagName = '详情展示标签';
        $createdAt = new \DateTimeImmutable('2024-04-01 00:00:00');
        $updatedAt = new \DateTimeImmutable('2024-04-02 12:34:56');
        $tag = $this->createTag($tagName, $createdAt, $updatedAt);

        $crawler = $client->request('GET', $this->generateAdminUrl(Action::DETAIL, [EA::ENTITY_ID => $tag->getId()]));
        $this->assertResponseIsSuccessful();

        $expected = match ($expectedKey) {
            'tag_name' => $tagName,
            'usage_zero' => '0',
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $updatedAt->format('Y-m-d H:i:s'),
            'inaccessible' => 'Inaccessible',
            default => $expectedKey,
        };

        $this->assertSelectorExpectation($crawler, $selector, $expected, $negate);
    }

    /**
     * @return iterable<array{string, string, bool}>
     */
    public static function provideDetailPageExpectations(): iterable
    {
        yield ['.field-group.field-text .field-value', 'tag_name', false];
        yield ['.field-group.field-integer .field-value', 'usage_zero', false];
        yield ['.field-group.field-integer .field-value', 'inaccessible', true];
        yield ['.field-group.field-datetime .field-value', 'created_at', false];
        yield ['.field-group.field-datetime .field-value', 'updated_at', false];
    }

    public function testDetailPageExpectationsProviderHasData(): void
    {
        $controller = $this->getControllerService();
        $labels = [];
        foreach ($controller->configureFields('detail') as $field) {
            if (is_object($field) && method_exists($field, 'getAsDto')) {
                $dto = $field->getAsDto();
                if ($dto->isDisplayedOn('detail')) {
                    $labels[] = $dto->getLabel();
                }
            }
        }

        self::assertContains('标签名称', $labels);
        self::assertContains('使用次数', $labels);
        self::assertContains('创建时间', $labels);
        self::assertContains('更新时间', $labels);

        $providerKeys = array_map(
            static fn (array $item): string => $item[1],
            iterator_to_array(self::provideDetailPageExpectations())
        );

        self::assertContains('tag_name', $providerKeys);
        self::assertContains('usage_zero', $providerKeys);
        self::assertContains('inaccessible', $providerKeys);
        self::assertContains('created_at', $providerKeys);
        self::assertContains('updated_at', $providerKeys);
    }

    public function testNewFormValidatesRequiredFields(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', $this->generateAdminUrl(Action::NEW));
        $this->assertResponseIsSuccessful();

        $invalidName = str_repeat('标签', 20);
        $form = $crawler->filter('form[name="Tag"]')->form([
            'Tag[name]' => $invalidName,
        ]);

        $crawler = $client->submit($form);
        $response = $client->getResponse();
        $this->assertFalse($response->isRedirection());

        $errorText = $crawler->filter('.invalid-feedback')->text();
        self::assertTrue(
            str_contains($errorText, '不能超过') || str_contains($errorText, 'too long'),
            '应提示名称长度限制'
        );
    }

    public function testUnauthorizedAccessReturnsRedirect(): void
    {
        $client = self::createClientWithDatabase();
        self::getClient($client);

        $client->catchExceptions(true);
        $client->request('GET', $this->generateAdminUrl(Action::INDEX));

        $response = $client->getResponse();
        self::assertTrue(
            $response->isForbidden() || $response->isRedirection(),
            '未登录或无权限时应阻止访问'
        );
    }

    private function assertSelectorExpectation(Crawler $crawler, string $selector, string $expected, bool $negate): void
    {
        $nodes = $crawler->filter($selector);
        self::assertGreaterThan(0, $nodes->count(), sprintf('Selector %s 应存在', $selector));

        $found = false;
        foreach ($nodes as $node) {
            if (str_contains(trim($node->textContent), $expected)) {
                $found = true;
                break;
            }
        }

        if ($negate) {
            self::assertFalse($found, sprintf('Selector %s 不应包含 %s', $selector, $expected));

            return;
        }

        self::assertTrue($found, sprintf('Selector %s 应包含 %s', $selector, $expected));
    }

    /**
     * @return TagCrudController
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(TagCrudController::class);
    }

    private function createTag(string $name, \DateTimeImmutable $createdAt, \DateTimeImmutable $updatedAt): Tag
    {
        $tag = new Tag();
        $tag->setName($name);
        $tag->setCreateTime($createdAt);
        $tag->setUpdateTime($updatedAt);

        $em = self::getEntityManager();
        $em->persist($tag);
        $em->flush();

        return $tag;
    }
}
