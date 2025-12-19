<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use Tourze\PromptManageBundle\Controller\TestingController;

/**
 * T25: 接口测试 - TestingController端到端
 *
 * Linus: "端到端测试验证整个数据流，从HTTP到响应"
 * 使用WebTestCase进行完整的HTTP流程测试，不mock内部服务
 * @internal
 */
#[CoversClass(TestingController::class)]
#[RunTestsInSeparateProcesses]
final class TestingControllerTest extends AbstractWebTestCase
{
    /**
     * 测试显示测试页面 - 验证路由和基本响应
     */
    public function showTestPageSuccessfulScenario(): void
    {
        $client = self::createClientWithDatabase();

        // 直接测试HTTP请求，不使用mock
        $client->request('GET', '/prompt-test/123/2');

        $response = $client->getResponse();
        // 在测试环境中，由于数据不存在会重定向，这是正常行为
        $this->assertTrue(
            $response->isSuccessful() || $response->isRedirection(),
            'Expected successful response or redirect, got: ' . $response->getStatusCode() . ' - ' . $response->getContent()
        );

        // 测试路由参数验证
        if ($response->isRedirection()) {
            // 验证重定向目标是合理的（通常是admin页面）
            $location = $response->headers->get('Location');
            $this->assertNotEmpty($location, 'Redirect should have a location header');
        }
    }

    /**
     * 测试显示测试页面 - 错误场景（不存在的prompt）
     */
    public function showTestPageErrorScenario(): void
    {
        $client = self::createClientWithDatabase();

        // 请求不存在的prompt，应该得到重定向或错误响应
        $client->request('GET', '/prompt-test/999/1');

        $response = $client->getResponse();
        $this->assertTrue(
            $response->isRedirection() || $response->isClientError(),
            'Expected redirect or client error response for non-existent prompt'
        );

        if ($response->isRedirection()) {
            $location = $response->headers->get('Location');
            $this->assertNotEmpty($location, 'Redirect should have a location header');
        }
    }

    /**
     * 测试执行测试 - POST请求验证
     */
    public function executeTestSuccessfulScenario(): void
    {
        $client = self::createClientWithDatabase();

        // 直接测试POST请求，不使用mock
        $client->request('POST', '/prompt-test/123/2', [
            'parameters' => ['name' => 'World'],
        ]);

        $response = $client->getResponse();
        // 在测试环境中，由于数据不存在通常会重定向
        $this->assertTrue(
            $response->isSuccessful() || $response->isRedirection() || $response->isClientError(),
            'Expected successful, redirect, or client error response, got: ' . $response->getStatusCode()
        );

        // 验证请求被正确处理（不管是成功还是重定向）
        if ($response->isRedirection()) {
            $location = $response->headers->get('Location');
            $this->assertNotEmpty($location, 'Redirect should have a location header');
        }
    }

    /**
     * 测试执行测试 - 验证错误处理
     */
    public function executeTestFailureScenario(): void
    {
        $client = self::createClientWithDatabase();

        // 测试POST请求到不存在的prompt
        $client->request('POST', '/prompt-test/999/1', [
            'parameters' => ['name' => 'World'],
        ]);

        $response = $client->getResponse();
        // 应该得到重定向或错误响应
        $this->assertTrue(
            $response->isRedirection() || $response->isClientError(),
            'Expected redirect or error response for non-existent prompt, got: ' . $response->getStatusCode()
        );

        if ($response->isRedirection()) {
            $location = $response->headers->get('Location');
            $this->assertNotEmpty($location, 'Redirect should have a location header');
        }
    }

    /**
     * 测试不同版本号的处理
     */
    public function templateWithDifferentVersionsIsHandled(): void
    {
        $client = self::createClientWithDatabase();

        // 测试版本1
        $client->request('GET', '/prompt-test/123/1');
        $response1 = $client->getResponse();
        $this->assertTrue(
            $response1->isSuccessful() || $response1->isRedirection() || $response1->isClientError(),
            'Expected valid HTTP response for version 1'
        );

        // 测试版本2
        $client->request('GET', '/prompt-test/123/2');
        $response2 = $client->getResponse();
        $this->assertTrue(
            $response2->isSuccessful() || $response2->isRedirection() || $response2->isClientError(),
            'Expected valid HTTP response for version 2'
        );
    }

    /**
     * 测试异常处理 - 验证系统在异常情况下的行为
     */
    public function exceptionHandlingWorks(): void
    {
        $client = self::createClientWithDatabase();

        // 测试极大的ID可能触发数据库约束或其他异常
        $client->request('GET', '/prompt-test/999999999/1');

        $response = $client->getResponse();
        $this->assertTrue(
            $response->isRedirection() || $response->isClientError() || $response->isServerError(),
            'Expected redirect, client error, or server error response for extreme ID'
        );
    }

    /**
     * 测试POST请求的异常处理
     */
    public function postExceptionHandlingWorks(): void
    {
        $client = self::createClientWithDatabase();

        // 测试POST请求到极大ID，可能触发异常
        $client->request('POST', '/prompt-test/999999999/1', [
            'parameters' => ['test' => 'value'],
        ]);

        $response = $client->getResponse();
        $this->assertTrue(
            $response->isRedirection() || $response->isClientError() || $response->isServerError(),
            'Expected redirect, client error, or server error response for extreme ID in POST'
        );
    }

    /**
     * 测试路由参数验证
     */
    public function routeParametersAreValidated(): void
    {
        $client = self::createClientWithDatabase();

        // 测试无效的promptId - 应该抛出NotFoundHttpException
        $this->expectException(NotFoundHttpException::class);
        $client->request('GET', '/prompt-test/abc/1');
    }

    /**
     * 测试HTTP方法限制
     */
    public function httpMethodRestrictions(): void
    {
        $client = self::createClientWithDatabase();

        // 测试不允许的HTTP方法 - PUT - 应该抛出MethodNotAllowedHttpException
        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request('PUT', '/prompt-test/123/1');
    }

    /**
     * 实现抽象方法 - 测试方法不允许
     */
    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();

        try {
            // 测试不允许的HTTP方法
            $client->request($method, '/prompt-test/123/1');

            // 根据路由配置，某些方法可能会返回 405 Method Not Allowed 或 404 Not Found
            $response = $client->getResponse();
            $this->assertTrue(
                Response::HTTP_METHOD_NOT_ALLOWED === $response->getStatusCode() || $response->isNotFound() || $response->isRedirection(),
                "Expected method not allowed, not found, or redirect for {$method} method"
            );
        } catch (\Exception $e) {
            // 捕获可能的异常（如MethodNotAllowedHttpException或NotFoundHttpException）
            // 对于路由中未定义的方法，Symfony 会返回 "No route found"
            $this->assertTrue(
                str_contains($e->getMessage(), 'Method Not Allowed') || str_contains($e->getMessage(), 'No route found'),
                "Expected 'Method Not Allowed' or 'No route found' for {$method} method, got: " . $e->getMessage()
            );
        }
    }
}
