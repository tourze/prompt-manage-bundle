<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\PromptManageBundle\Service\TestingServiceInterface;

/**
 * 提示词参数API控制器
 *
 * Linus: "单一职责" - 只处理参数获取API
 */
final class TestingParametersController extends AbstractController
{
    public function __construct(
        private readonly TestingServiceInterface $testingService,
    ) {
    }

    /**
     * 获取参数定义 (AJAX支持)
     */
    #[Route(path: '/prompt-test/parameters/{promptId}/{version}', name: 'prompt_test_parameters', methods: ['GET'], requirements: ['promptId' => '\d+', 'version' => '\d+'])]
    public function __invoke(int $promptId, int $version): JsonResponse
    {
        try {
            $testData = $this->testingService->getTestData($promptId, $version);

            if (isset($testData['error'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => $testData['error'],
                ], Response::HTTP_BAD_REQUEST);
            }

            return new JsonResponse([
                'success' => true,
                'parameters' => $testData['parameters'] ?? [],
                'template' => $testData['template'] ?? '',
                'prompt_name' => $testData['prompt_name'] ?? 'Unknown',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
