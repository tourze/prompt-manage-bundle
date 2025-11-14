# 提示词管理 Bundle (Prompt Manage Bundle)

[English](README.md) | [中文](README.zh-CN.md)

一个专为 Symfony 应用程序设计的综合性 AI 提示词管理系统，提供版本控制、测试、模板渲染和管理界面功能。

## 🌟 核心功能

- **🔧 核心管理**：完整的提示词 CRUD 操作，支持项目分类和标签系统
- **📝 版本控制**：自动版本跟踪，支持版本比较和回滚功能
- **🧪 测试系统**：模板参数解析和渲染，提供预览功能
- **🛡️ 安全与审计**：基于角色的权限控制和全面的审计日志
- **⚙️ 管理界面**：集成 EasyAdmin 提供直观的管理体验
- **🎯 模板引擎**：支持 Jinja2 风格模板语法和多种渲染引擎

## 📋 环境要求

- PHP 8.1+
- Symfony 7.3+
- Doctrine ORM 3.0+
- EasyAdminBundle 4+

## 🚀 安装

```bash
composer require tourze/prompt-manage-bundle
```

## ⚙️ 配置

```yaml
# config/packages/tourze_prompt_manage.yaml
tourze_prompt_manage:
    # 默认模板引擎 (twig, jinja2, 等)
    default_engine: twig

    # 安全设置
    security:
        enable_audit: true
        default_roles: ['ROLE_PROMPT_VIEWER']

    # 测试参数
    testing:
        max_execution_time: 30
        enable_sandbox: true
```

## 💡 使用方法

### 基础提示词管理

```php
use Tourze\PromptManageBundle\Service\PromptServiceInterface;

class YourController extends AbstractController
{
    public function __construct(
        private readonly PromptServiceInterface $promptService,
    ) {}

    // 创建新提示词
    $prompt = $this->promptService->createPrompt(
        name: '客服模板',
        content: '您好 {{name}}，我们如何帮助您处理 {{topic}}？',
        project: $project,
        tags: ['客服', '客户支持']
    );

    // 获取指定版本的提示词
    $prompt = $this->promptService->getPromptWithVersion($promptId, $version);
}
```

### 模板测试

```php
use Tourze\PromptManageBundle\Service\TestingServiceInterface;

class TestController extends AbstractController
{
    public function __construct(
        private readonly TestingServiceInterface $testingService,
    ) {}

    // 测试提示词并传入参数
    $result = $this->testingService->testPrompt(
        prompt: $prompt,
        version: 1,
        parameters: [
            'name' => '张三',
            'topic' => '账户问题'
        ]
    );

    echo $result->getRenderedContent();
    // 输出: "您好 张三，我们如何帮助您处理 账户问题？"
}
```

### 模板参数提取

```php
use Tourze\PromptManageBundle\Service\ParameterExtractorInterface;

// 从模板中提取参数
$parameters = $parameterExtractor->extractParameters(
    '您好 {{name}}，您的订单 #{{order_id}} 状态为 {{status}}'
);
// 返回: ['name', 'order_id', 'status']
```

### 版本管理

```php
// 创建新版本
$newVersion = $promptService->createNewVersion(
    $prompt,
    '更新了模板内容，添加了新变量'
);

// 比较版本
$diff = $promptService->compareVersions($prompt, 1, 2);

// 切换到指定版本
$promptService->switchToVersion($prompt, 2);
```

## 📊 实体模型

### Prompt (提示词)

代表 AI 提示词的主要实体，包含版本控制和元数据。

```php
#[ORM\Entity]
class Prompt
{
    private ?int $id = null;
    private string $name;           // 提示词名称
    private ?Project $project = null; // 所属项目
    private int $currentVersion = 1;  // 当前版本号

    // 关联关系
    private Collection $versions;    // 版本列表
    private Collection $tags;        // 标签列表

    // 时间戳和审计字段
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;
    private ?string $createdBy = null;
}
```

### PromptVersion (提示词版本)

表示提示词的特定版本，包含完整的内容跟踪。

```php
#[ORM\Entity]
class PromptVersion
{
    private ?int $id = null;
    private int $version;              // 版本号
    private string $content;           // 模板内容
    private array $variables = [];     // 模板变量

    // 关联关系
    private ?Prompt $prompt = null;

    // 元数据
    private ?\DateTimeImmutable $createdAt = null;
    private ?string $createdBy = null;
    private ?string $changeDescription = null;
}
```

### Project 和 Tag

用于分类和组织提示词的组织性实体。

## 🛠️ 核心服务

### PromptServiceInterface

提示词管理的核心服务：

- `createPrompt()`: 创建新提示词和元数据
- `updatePrompt()`: 更新现有提示词
- `getPromptWithVersion()`: 检索特定版本
- `deletePrompt()`: 软删除提示词
- `searchPrompts()`: 使用过滤器搜索

### TestingServiceInterface

模板测试和渲染功能：

- `testPrompt()`: 使用参数测试提示词
- `validateTemplate()`: 验证模板语法
- `previewPrompt()`: 生成预览而不保存
- `extractVariables()`: 分析模板变量

### VersionServiceInterface

版本控制操作：

- `createNewVersion()`: 创建新提示词版本
- `compareVersions()`: 生成版本差异
- `switchToVersion()`: 切换活动版本
- `getVersionHistory()`: 列出所有版本

## 🌐 API 接口

### 测试接口

```bash
# GET - 获取测试表单和提取的参数
GET /prompt-test/{promptId}/{version}

# POST - 提交测试参数并获取渲染结果
POST /prompt-test/{promptId}/{version}
Content-Type: application/json

{
    "parameters": {
        "name": "张三",
        "topic": "技术支持"
    }
}
```

### 管理界面

Bundle 提供 EasyAdmin 控制器用于：

- `/admin/prompt` - 提示词管理
- `/admin/project` - 项目管理
- `/admin/tag` - 标签管理
- `/admin/prompt-version` - 版本管理

## 🎨 模板引擎

### Twig 引擎 (默认)

```twig
您好 {{ name }}，您的订单 #{{ order_id }} 状态为 {{ status }}。

{% if urgent %}
这是紧急情况！请立即回复。
{% endif %}
```

### Jinja2 兼容

```jinja2
您好 {{ name }}，您的订单 #{{ order_id }} 状态为 {{ status }}。

{% for item in items %}
- {{ item.name }}: {{ item.price }}
{% endfor %}
```

## 🔒 安全性

### 基于角色的访问控制

- **ROLE_PROMPT_ADMIN**: 完全访问权限
- **ROLE_PROMPT_EDITOR**: 创建和编辑权限
- **ROLE_PROMPT_VIEWER**: 只读访问权限

### 审计日志

所有操作都会自动记录：

- 用户信息
- 时间戳
- 操作类型
- 变更数据
- IP 地址和用户代理

## 🛠️ 开发

### 运行测试

```bash
# 运行单元测试
composer test

# 运行集成测试
composer test:integration

# 生成覆盖率报告
composer test:coverage
```

### 代码质量

```bash
# 静态分析
composer analyze

# 代码风格检查
composer cs-check

# 修复代码风格
composer cs-fix
```

## 🤝 贡献

1. Fork 本仓库
2. 创建功能分支 (`git checkout -b feature/amazing-feature`)
3. 提交您的更改 (`git commit -m 'Add amazing feature'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 打开 Pull Request

## 📄 许可证

本 Bundle 使用 MIT 许可证发布。详情请参阅 [LICENSE](LICENSE) 文件。

## 📞 支持

- 📖 [文档](docs/)
- 🐛 [问题跟踪](https://github.com/tourze/prompt-manage-bundle/issues)
- 💬 [讨论区](https://github.com/tourze/prompt-manage-bundle/discussions)

## 📝 更新日志

查看 [CHANGELOG.md](CHANGELOG.md) 了解变更列表和版本历史。
