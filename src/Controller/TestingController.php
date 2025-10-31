<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\PromptManageBundle\Service\TestingServiceInterface;

/**
 * 提示词测试控制器 - 外部接口简化设计
 *
 * Linus: "简单胜于复杂" - 统一入口，根据HTTP方法路由
 */
final class TestingController extends AbstractController
{
    public function __construct(
        private readonly TestingServiceInterface $testingService,
    ) {
    }

    /**
     * 统一入口 - 根据HTTP方法处理不同逻辑
     */
    #[Route(path: '/prompt-test/{promptId}/{version}', name: 'prompt_test', methods: ['GET', 'POST'], requirements: ['promptId' => '\d+', 'version' => '\d+'])]
    public function __invoke(int $promptId, int $version, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->executeTest($promptId, $version, $request);
        }

        return $this->showTestPage($promptId, $version);
    }

    /**
     * 显示测试页面
     */
    private function showTestPage(int $promptId, int $version): Response
    {
        try {
            $testData = $this->testingService->getTestData($promptId, $version);

            if (isset($testData['error'])) {
                $errorMessage = is_string($testData['error']) ? $testData['error'] : 'Unknown error';
                $this->addFlash('danger', 'Failed to load test data: ' . $errorMessage);

                return $this->redirectToRoute('admin');
            }

            return $this->render('@PromptManage/testing/test_page.html.twig', [
                'test_data' => $testData,
                'prompt_id' => $promptId,
                'version' => $version,
                'parameters' => $testData['parameters'] ?? [],
                'template' => $testData['template'] ?? '',
                'prompt_name' => $testData['prompt_name'] ?? 'Unknown',
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'System error: ' . $e->getMessage());

            return $this->redirectToRoute('admin');
        }
    }

    /**
     * 执行测试
     */
    private function executeTest(int $promptId, int $version, Request $request): Response
    {
        try {
            $parameters = $this->extractParametersFromRequest($request);
            $result = $this->testingService->executeTest($promptId, $version, $parameters);
            $testData = $this->testingService->getTestData($promptId, $version);

            return $this->render('@PromptManage/testing/test_page.html.twig', [
                'test_data' => $testData,
                'test_result' => $result,
                'prompt_id' => $promptId,
                'version' => $version,
                'parameters' => $testData['parameters'] ?? [],
                'parameter_values' => $parameters,
                'template' => $testData['template'] ?? '',
                'prompt_name' => $testData['prompt_name'] ?? 'Unknown',
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Test execution failed: ' . $e->getMessage());

            return $this->redirectToRoute('prompt_test', [
                'promptId' => $promptId,
                'version' => $version,
            ]);
        }
    }

    /**
     * 从请求中提取参数并确保类型安全
     * @return array<string, mixed>
     */
    private function extractParametersFromRequest(Request $request): array
    {
        $parametersRaw = $request->request->all('parameters');
        $parameters = [];

        // Symfony Request::all() 总是返回 array，但内容可能是 mixed 类型
        foreach ($parametersRaw as $key => $value) {
            if (is_string($key)) {
                $parameters[$key] = $this->sanitizeParameterValue($value);
            }
        }

        return $parameters;
    }

    /**
     * 清理参数值确保类型安全
     */
    private function sanitizeParameterValue(mixed $value): mixed
    {
        if (is_string($value) || is_numeric($value) || is_bool($value) || is_null($value)) {
            return $value;
        }

        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value);

            return false !== $encoded ? $encoded : '';
        }

        return '';
    }
}
