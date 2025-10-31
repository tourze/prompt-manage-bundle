<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\PromptManageBundle\Entity\Project;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\Tag;

class PromptFixtures extends Fixture implements DependentFixtureInterface
{
    public const PROMPT_CUSTOMER_GREETING = 'prompt-customer-greeting';
    public const PROMPT_PRODUCT_DESCRIPTION = 'prompt-product-description';
    public const PROMPT_CHATBOT_GENERAL = 'prompt-chatbot-general';
    public const PROMPT_BLOG_ARTICLE = 'prompt-blog-article';
    public const PROMPT_DATA_SUMMARY = 'prompt-data-summary';
    public const PROMPT_LEARNING_PLAN = 'prompt-learning-plan';
    public const PROMPT_CODE_REVIEW = 'prompt-code-review';
    public const PROMPT_TRANSLATION_EN_CN = 'prompt-translation-en-cn';

    public function load(ObjectManager $manager): void
    {
        /** @var Project $ecommerceProject */
        $ecommerceProject = $this->getReference(ProjectFixtures::PROJECT_ECOMMERCE, Project::class);
        /** @var Project $chatbotProject */
        $chatbotProject = $this->getReference(ProjectFixtures::PROJECT_CHATBOT, Project::class);
        /** @var Project $contentProject */
        $contentProject = $this->getReference(ProjectFixtures::PROJECT_CONTENT, Project::class);
        /** @var Project $analysisProject */
        $analysisProject = $this->getReference(ProjectFixtures::PROJECT_ANALYSIS, Project::class);
        /** @var Project $educationProject */
        $educationProject = $this->getReference(ProjectFixtures::PROJECT_EDUCATION, Project::class);

        /** @var Tag $customerServiceTag */
        $customerServiceTag = $this->getReference(TagFixtures::TAG_CUSTOMER_SERVICE, Tag::class);
        /** @var Tag $marketingTag */
        $marketingTag = $this->getReference(TagFixtures::TAG_MARKETING, Tag::class);
        /** @var Tag $contentCreationTag */
        $contentCreationTag = $this->getReference(TagFixtures::TAG_CONTENT_CREATION, Tag::class);
        /** @var Tag $dataAnalysisTag */
        $dataAnalysisTag = $this->getReference(TagFixtures::TAG_DATA_ANALYSIS, Tag::class);
        /** @var Tag $educationTag */
        $educationTag = $this->getReference(TagFixtures::TAG_EDUCATION, Tag::class);
        /** @var Tag $conversationTag */
        $conversationTag = $this->getReference(TagFixtures::TAG_CONVERSATION, Tag::class);
        /** @var Tag $writingTag */
        $writingTag = $this->getReference(TagFixtures::TAG_WRITING, Tag::class);
        /** @var Tag $codingTag */
        $codingTag = $this->getReference(TagFixtures::TAG_CODING, Tag::class);
        /** @var Tag $translationTag */
        $translationTag = $this->getReference(TagFixtures::TAG_TRANSLATION, Tag::class);

        $prompts = [
            [
                'name' => '客服问候语',
                'project' => $ecommerceProject,
                'tags' => [$customerServiceTag, $conversationTag],
                'reference' => self::PROMPT_CUSTOMER_GREETING,
            ],
            [
                'name' => '商品描述生成器',
                'project' => $ecommerceProject,
                'tags' => [$marketingTag, $contentCreationTag],
                'reference' => self::PROMPT_PRODUCT_DESCRIPTION,
            ],
            [
                'name' => '通用聊天机器人',
                'project' => $chatbotProject,
                'tags' => [$conversationTag],
                'reference' => self::PROMPT_CHATBOT_GENERAL,
            ],
            [
                'name' => '博客文章写作助手',
                'project' => $contentProject,
                'tags' => [$contentCreationTag, $writingTag],
                'reference' => self::PROMPT_BLOG_ARTICLE,
            ],
            [
                'name' => '数据摘要生成器',
                'project' => $analysisProject,
                'tags' => [$dataAnalysisTag],
                'reference' => self::PROMPT_DATA_SUMMARY,
            ],
            [
                'name' => '个性化学习计划',
                'project' => $educationProject,
                'tags' => [$educationTag],
                'reference' => self::PROMPT_LEARNING_PLAN,
            ],
            [
                'name' => '代码审查助手',
                'project' => null, // 独立提示词，不属于特定项目
                'tags' => [$codingTag],
                'reference' => self::PROMPT_CODE_REVIEW,
            ],
            [
                'name' => '英中翻译器',
                'project' => $contentProject,
                'tags' => [$translationTag, $contentCreationTag],
                'reference' => self::PROMPT_TRANSLATION_EN_CN,
            ],
        ];

        foreach ($prompts as $promptData) {
            $prompt = new Prompt();
            $prompt->setName($promptData['name']);

            if (null !== $promptData['project']) {
                $prompt->setProject($promptData['project']);
            }

            foreach ($promptData['tags'] as $tag) {
                $prompt->addTag($tag);
            }

            $manager->persist($prompt);
            $this->addReference($promptData['reference'], $prompt);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ProjectFixtures::class,
            TagFixtures::class,
        ];
    }
}
