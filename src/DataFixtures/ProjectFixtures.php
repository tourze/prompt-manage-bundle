<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\PromptManageBundle\Entity\Project;

class ProjectFixtures extends Fixture
{
    public const PROJECT_ECOMMERCE = 'project-ecommerce';
    public const PROJECT_CHATBOT = 'project-chatbot';
    public const PROJECT_CONTENT = 'project-content';
    public const PROJECT_ANALYSIS = 'project-analysis';
    public const PROJECT_EDUCATION = 'project-education';

    public function load(ObjectManager $manager): void
    {
        $projects = [
            [
                'name' => 'E-commerce Platform',
                'description' => '电商平台相关的AI提示词管理，包括商品推荐、客服对话、营销文案等场景',
                'reference' => self::PROJECT_ECOMMERCE,
            ],
            [
                'name' => 'Intelligent Chatbot',
                'description' => '智能聊天机器人项目，涵盖多领域对话场景和知识问答',
                'reference' => self::PROJECT_CHATBOT,
            ],
            [
                'name' => 'Content Generation',
                'description' => '内容生成项目，用于文章写作、创意文案、社媒内容等自动化生成',
                'reference' => self::PROJECT_CONTENT,
            ],
            [
                'name' => 'Data Analysis Assistant',
                'description' => '数据分析助手项目，提供数据解读、报告生成、可视化建议等功能',
                'reference' => self::PROJECT_ANALYSIS,
            ],
            [
                'name' => 'Educational Platform',
                'description' => '教育平台项目，包含个性化学习、题目生成、知识总结等教学辅助功能',
                'reference' => self::PROJECT_EDUCATION,
            ],
        ];

        foreach ($projects as $projectData) {
            $project = new Project();
            $project->setName($projectData['name']);
            $project->setDescription($projectData['description']);

            $manager->persist($project);
            $this->addReference($projectData['reference'], $project);
        }

        $manager->flush();
    }
}
