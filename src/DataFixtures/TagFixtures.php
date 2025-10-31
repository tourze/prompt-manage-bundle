<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\PromptManageBundle\Entity\Tag;

class TagFixtures extends Fixture
{
    public const TAG_CUSTOMER_SERVICE = 'tag-customer-service';
    public const TAG_MARKETING = 'tag-marketing';
    public const TAG_CONTENT_CREATION = 'tag-content-creation';
    public const TAG_DATA_ANALYSIS = 'tag-data-analysis';
    public const TAG_EDUCATION = 'tag-education';
    public const TAG_CONVERSATION = 'tag-conversation';
    public const TAG_RECOMMENDATION = 'tag-recommendation';
    public const TAG_WRITING = 'tag-writing';
    public const TAG_TRANSLATION = 'tag-translation';
    public const TAG_DEBUGGING = 'tag-debugging';
    public const TAG_CODING = 'tag-coding';
    public const TAG_RESEARCH = 'tag-research';

    public function load(ObjectManager $manager): void
    {
        $tags = [
            ['name' => '客服', 'reference' => self::TAG_CUSTOMER_SERVICE],
            ['name' => '营销', 'reference' => self::TAG_MARKETING],
            ['name' => '内容创作', 'reference' => self::TAG_CONTENT_CREATION],
            ['name' => '数据分析', 'reference' => self::TAG_DATA_ANALYSIS],
            ['name' => '教育', 'reference' => self::TAG_EDUCATION],
            ['name' => '对话', 'reference' => self::TAG_CONVERSATION],
            ['name' => '推荐', 'reference' => self::TAG_RECOMMENDATION],
            ['name' => '写作', 'reference' => self::TAG_WRITING],
            ['name' => '翻译', 'reference' => self::TAG_TRANSLATION],
            ['name' => '调试', 'reference' => self::TAG_DEBUGGING],
            ['name' => '编程', 'reference' => self::TAG_CODING],
            ['name' => '研究', 'reference' => self::TAG_RESEARCH],
        ];

        foreach ($tags as $tagData) {
            $tag = new Tag();
            $tag->setName($tagData['name']);

            $manager->persist($tag);
            $this->addReference($tagData['reference'], $tag);
        }

        $manager->flush();
    }
}
