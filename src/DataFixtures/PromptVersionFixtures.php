<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\PromptVersion;

class PromptVersionFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Prompt $customerGreetingPrompt */
        $customerGreetingPrompt = $this->getReference(PromptFixtures::PROMPT_CUSTOMER_GREETING, Prompt::class);
        /** @var Prompt $productDescriptionPrompt */
        $productDescriptionPrompt = $this->getReference(PromptFixtures::PROMPT_PRODUCT_DESCRIPTION, Prompt::class);
        /** @var Prompt $chatbotGeneralPrompt */
        $chatbotGeneralPrompt = $this->getReference(PromptFixtures::PROMPT_CHATBOT_GENERAL, Prompt::class);
        /** @var Prompt $blogArticlePrompt */
        $blogArticlePrompt = $this->getReference(PromptFixtures::PROMPT_BLOG_ARTICLE, Prompt::class);
        /** @var Prompt $dataSummaryPrompt */
        $dataSummaryPrompt = $this->getReference(PromptFixtures::PROMPT_DATA_SUMMARY, Prompt::class);
        /** @var Prompt $learningPlanPrompt */
        $learningPlanPrompt = $this->getReference(PromptFixtures::PROMPT_LEARNING_PLAN, Prompt::class);
        /** @var Prompt $codeReviewPrompt */
        $codeReviewPrompt = $this->getReference(PromptFixtures::PROMPT_CODE_REVIEW, Prompt::class);
        /** @var Prompt $translationPrompt */
        $translationPrompt = $this->getReference(PromptFixtures::PROMPT_TRANSLATION_EN_CN, Prompt::class);

        $versions = [
            // 客服问候语版本
            [
                'prompt' => $customerGreetingPrompt,
                'version' => 1,
                'content' => '你好！欢迎来到我们的在线商店。我是您的专属客服助手，很高兴为您服务。请问有什么可以帮助您的吗？',
                'changeNote' => '初始版本',
            ],
            [
                'prompt' => $customerGreetingPrompt,
                'version' => 2,
                'content' => '您好！欢迎光临 {店铺名称}！我是您的专属购物顾问 {客服名称}，随时为您提供专业的购物建议和贴心服务。请问今天想要了解什么产品呢？',
                'changeNote' => '增加变量占位符，更加个性化',
            ],

            // 商品描述生成器版本
            [
                'prompt' => $productDescriptionPrompt,
                'version' => 1,
                'content' => '请为以下商品撰写吸引人的描述：\n\n商品名称：{商品名称}\n主要特点：{商品特点}\n适用人群：{目标客户}\n\n要求：\n1. 突出商品的核心优势\n2. 语言生动有趣\n3. 包含情感化描述\n4. 控制在200字以内',
                'changeNote' => '初始版本，基础商品描述模板',
            ],
            [
                'prompt' => $productDescriptionPrompt,
                'version' => 2,
                'content' => '作为专业的电商文案师，请为以下商品撰写高转化率的商品描述：\n\n【商品信息】\n- 商品名称：{商品名称}\n- 核心特点：{商品特点}\n- 目标客户：{目标客户}\n- 价格区间：{价格信息}\n- 使用场景：{使用场景}\n\n【撰写要求】\n1. 开头抓人眼球，突出痛点解决方案\n2. 中间详述核心卖点和使用价值\n3. 结尾营造紧迫感，促进购买决策\n4. 全文控制在300字内，段落分明\n5. 使用数字和具体描述增强说服力',
                'changeNote' => '优化结构，增加营销心理学元素',
            ],

            // 通用聊天机器人版本
            [
                'prompt' => $chatbotGeneralPrompt,
                'version' => 1,
                'content' => '你是一个友善、有帮助的AI助手。请根据用户的问题提供准确、有用的回答。保持对话自然流畅，如果不确定答案，请诚实说明。',
                'changeNote' => '基础对话机器人设定',
            ],
            [
                'prompt' => $chatbotGeneralPrompt,
                'version' => 2,
                'content' => '你是一个专业、友善且富有同理心的AI助手。你的使命是为用户提供准确、有价值的帮助。\n\n【对话原则】\n1. 始终保持礼貌和耐心\n2. 提供准确、实用的信息\n3. 如遇不确定问题，主动澄清或承认不知\n4. 根据用户需求调整回答的详细程度\n5. 必要时主动提供相关建议或后续步骤\n\n【回答风格】\n- 语言简洁明了，避免冗余\n- 适当使用结构化格式（如列表、步骤）\n- 体现专业性的同时保持亲和力',
                'changeNote' => '增加详细的对话原则和风格指导',
            ],

            // 博客文章写作助手版本
            [
                'prompt' => $blogArticlePrompt,
                'version' => 1,
                'content' => '请帮我撰写一篇关于 "{主题}" 的博客文章。\n\n要求：\n1. 文章结构清晰（引言-正文-结论）\n2. 字数控制在1000-1500字\n3. 语言生动有趣\n4. 包含实用信息',
                'changeNote' => '初始版本',
            ],
            [
                'prompt' => $blogArticlePrompt,
                'version' => 2,
                'content' => '作为专业的内容创作者，请为以下主题撰写一篇高质量的博客文章：\n\n【文章主题】{主题}\n【目标读者】{目标读者}\n【文章目标】{文章目标}\n【关键词】{SEO关键词}\n\n【撰写要求】\n1. 标题：吸引眼球，包含主关键词\n2. 引言：明确价值主张，激发继续阅读兴趣\n3. 正文：\n   - 使用小标题分段，便于阅读\n   - 提供具体例子和数据支撑\n   - 包含可操作的建议或步骤\n4. 结论：总结要点，提供行动建议\n5. 字数：1500-2000字\n6. SEO优化：自然融入关键词，添加内链建议',
                'changeNote' => '专业化改版，增加SEO优化要求',
            ],

            // 数据摘要生成器版本
            [
                'prompt' => $dataSummaryPrompt,
                'version' => 1,
                'content' => '请对以下数据进行分析和摘要：\n\n{数据内容}\n\n请提供：\n1. 关键指标总结\n2. 主要趋势分析\n3. 重要发现或异常\n4. 建议或后续行动',
                'changeNote' => '基础数据分析模板',
            ],

            // 个性化学习计划版本
            [
                'prompt' => $learningPlanPrompt,
                'version' => 1,
                'content' => '请为以下学习需求制定个性化学习计划：\n\n学习者信息：\n- 当前水平：{当前水平}\n- 学习目标：{学习目标}\n- 可用时间：{可用时间}\n- 学习偏好：{学习偏好}\n\n请提供：\n1. 学习路径规划\n2. 阶段性目标设定\n3. 推荐学习资源\n4. 学习进度跟踪建议',
                'changeNote' => '个性化学习计划模板',
            ],

            // 代码审查助手版本
            [
                'prompt' => $codeReviewPrompt,
                'version' => 1,
                'content' => '请对以下代码进行专业审查：\n\n```{编程语言}\n{代码内容}\n```\n\n请从以下方面进行评估：\n1. 代码质量和可读性\n2. 性能优化建议\n3. 安全性问题\n4. 最佳实践遵循情况\n5. 潜在的bug或问题\n6. 改进建议',
                'changeNote' => '代码审查标准模板',
            ],

            // 英中翻译器版本
            [
                'prompt' => $translationPrompt,
                'version' => 1,
                'content' => '请将以下英文内容翻译成中文：\n\n{英文内容}\n\n翻译要求：\n1. 准确传达原文含义\n2. 符合中文表达习惯\n3. 保持原文的语气和风格\n4. 专业术语使用标准译法',
                'changeNote' => '基础英中翻译模板',
            ],
            [
                'prompt' => $translationPrompt,
                'version' => 2,
                'content' => '作为专业的英中翻译专家，请将以下内容进行高质量翻译：\n\n【原文】\n{英文内容}\n\n【翻译要求】\n1. 准确性：忠实原文，不遗漏不添加\n2. 流畅性：符合中文表达习惯，读起来自然\n3. 专业性：术语翻译准确，上下文一致\n4. 语域适配：保持原文的正式程度和语气\n5. 本土化：必要时进行文化背景适配\n\n【输出格式】\n- 提供完整翻译\n- 标注重要术语的翻译选择\n- 如有歧义，提供备选翻译方案',
                'changeNote' => '专业化改进，增加翻译质量控制',
            ],
        ];

        foreach ($versions as $versionData) {
            $version = new PromptVersion();
            $version->setPrompt($versionData['prompt']);
            $version->setVersion($versionData['version']);
            $version->setContent($versionData['content']);
            $version->setChangeNote($versionData['changeNote']);

            $manager->persist($version);

            // 更新 Prompt 的当前版本号
            $versionData['prompt']->setCurrentVersion($versionData['version']);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            PromptFixtures::class,
        ];
    }
}
