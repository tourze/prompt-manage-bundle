# Prompt Manage Bundle

[中文](README.zh-CN.md) | English

A comprehensive AI prompt management system for Symfony applications that provides version control, testing, template rendering, and administrative interface capabilities.

## Features

- **🔧 Core Management**: Complete CRUD operations for prompts with project categorization and tag system
- **📝 Version Control**: Automatic version tracking with comparison and rollback capabilities
- **🧪 Testing System**: Template parameter parsing and rendering with preview functionality
- **🛡️ Security & Audit**: Role-based permissions and comprehensive audit logging
- **⚙️ Admin Interface**: EasyAdmin integration for intuitive management
- **🎯 Template Engine**: Jinja2-style template syntax support with multiple rendering engines

## Requirements

- PHP 8.1+
- Symfony 7.3+
- Doctrine ORM 3.0+
- EasyAdminBundle 4+

## Installation

```bash
composer require tourze/prompt-manage-bundle
```

## Configuration

```yaml
# config/packages/tourze_prompt_manage.yaml
tourze_prompt_manage:
    # Default template engine (twig, jinja2, etc.)
    default_engine: twig

    # Security settings
    security:
        enable_audit: true
        default_roles: ['ROLE_PROMPT_VIEWER']

    # Testing parameters
    testing:
        max_execution_time: 30
        enable_sandbox: true
```

## Usage

### Basic Prompt Management

```php
use Tourze\PromptManageBundle\Service\PromptServiceInterface;

class YourController extends AbstractController
{
    public function __construct(
        private readonly PromptServiceInterface $promptService,
    ) {}

    // Create a new prompt
    $prompt = $this->promptService->createPrompt(
        name: 'Customer Support Template',
        content: 'Hello {{name}}, how can we help you with {{topic}}?',
        project: $project,
        tags: ['support', 'customer']
    );

    // Get prompt with version
    $prompt = $this->promptService->getPromptWithVersion($promptId, $version);
}
```

### Template Testing

```php
use Tourze\PromptManageBundle\Service\TestingServiceInterface;

class TestController extends AbstractController
{
    public function __construct(
        private readonly TestingServiceInterface $testingService,
    ) {}

    // Test prompt with parameters
    $result = $this->testingService->testPrompt(
        prompt: $prompt,
        version: 1,
        parameters: [
            'name' => 'John Doe',
            'topic' => 'account issues'
        ]
    );

    echo $result->getRenderedContent();
    // Output: "Hello John Doe, how can we help you with account issues?"
}
```

### Template Parameter Extraction

```php
use Tourze\PromptManageBundle\Service\ParameterExtractorInterface;

// Extract parameters from template
$parameters = $parameterExtractor->extractParameters(
    'Hello {{name}}, your order #{{order_id}} is {{status}}'
);
// Returns: ['name', 'order_id', 'status']
```

### Version Management

```php
// Create new version
$newVersion = $promptService->createNewVersion(
    $prompt,
    'Updated template content with new variables'
);

// Compare versions
$diff = $promptService->compareVersions($prompt, 1, 2);

// Switch to specific version
$promptService->switchToVersion($prompt, 2);
```

## Entities

### Prompt

The main entity representing an AI prompt with version control and metadata.

```php
#[ORM\Entity]
class Prompt
{
    private ?int $id = null;
    private string $name;
    private ?Project $project = null;
    private int $currentVersion = 1;

    // Relations
    private Collection $versions;
    private Collection $tags;

    // Timestamps and audit fields
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;
    private ?string $createdBy = null;
}
```

### PromptVersion

Represents a specific version of a prompt with complete content tracking.

```php
#[ORM\Entity]
class PromptVersion
{
    private ?int $id = null;
    private int $version;
    private string $content;
    private array $variables = [];

    // Relations
    private ?Prompt $prompt = null;

    // Metadata
    private ?\DateTimeImmutable $createdAt = null;
    private ?string $createdBy = null;
    private ?string $changeDescription = null;
}
```

### Project and Tag

Organizational entities for categorizing and managing prompts.

## Services

### PromptServiceInterface

Core service for prompt management operations:

- `createPrompt()`: Create new prompts with metadata
- `updatePrompt()`: Update existing prompts
- `getPromptWithVersion()`: Retrieve specific versions
- `deletePrompt()`: Soft delete prompts
- `searchPrompts()`: Search with filters

### TestingServiceInterface

Template testing and rendering functionality:

- `testPrompt()`: Test prompts with parameters
- `validateTemplate()`: Validate template syntax
- `previewPrompt()`: Generate preview without saving
- `extractVariables()`: Analyze template variables

### VersionServiceInterface

Version control operations:

- `createNewVersion()`: Create new prompt versions
- `compareVersions()`: Generate version differences
- `switchToVersion()`: Change active version
- `getVersionHistory()`: List all versions

## API Endpoints

### Testing API

```bash
# GET - Get test form and extracted parameters
GET /prompt-test/{promptId}/{version}

# POST - Submit test parameters and get rendered result
POST /prompt-test/{promptId}/{version}
Content-Type: application/json

{
    "parameters": {
        "name": "John",
        "topic": "support"
    }
}
```

### Admin Interface

The bundle provides EasyAdmin controllers for:

- `/admin/prompt` - Prompt management
- `/admin/project` - Project management
- `/admin/tag` - Tag management
- `/admin/prompt-version` - Version management

## Template Engines

### Twig Engine (Default)

```twig
Hello {{ name }}, your order #{{ order_id }} is {{ status }}.

{% if urgent %}
This is urgent! Please respond immediately.
{% endif %}
```

### Jinja2 Compatibility

```jinja2
Hello {{ name }}, your order #{{ order_id }} is {{ status }}.

{% for item in items %}
- {{ item.name }}: {{ item.price }}
{% endfor %}
```

## Security

### Role-Based Access Control

- **ROLE_PROMPT_ADMIN**: Full access to all operations
- **ROLE_PROMPT_EDITOR**: Create and edit prompts
- **ROLE_PROMPT_VIEWER**: Read-only access

### Audit Logging

All operations are automatically logged with:

- User information
- Timestamp
- Operation type
- Changed data
- IP address and user agent

## Development

### Running Tests

```bash
# Run unit tests
composer test

# Run integration tests
composer test:integration

# Generate coverage report
composer test:coverage
```

### Code Quality

```bash
# Static analysis
composer analyze

# Code style check
composer cs-check

# Fix code style
composer cs-fix
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.

## Support

- 📖 [Documentation](docs/)
- 🐛 [Issue Tracker](https://github.com/tourze/prompt-manage-bundle/issues)
- 💬 [Discussions](https://github.com/tourze/prompt-manage-bundle/discussions)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes and version history.
