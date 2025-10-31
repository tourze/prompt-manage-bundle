# 提示词管理Bundle - FRD拆分方案

## 🎯 总体架构

基于 `PRD.md` 需求文档，将AI提示词管理模块按照业务相关性和技术复杂度拆分为**4个独立特性**：

## 📋 特性列表

### 1. [prompt-core-management](./prompt-core-management.md) - 提示词核心管理
**优先级**: P0 (核心基础)  
**估时**: 22.5小时  

**核心职责**:
- 提示词CRUD操作（创建、编辑、查询、软删除）
- 项目维度分类管理
- 标签系统（多对多关系）
- Jinja2风格模板语法支持
- EasyAdmin基础界面

**关键组件**: `PromptService`, `Prompt/Project/Tag Entity`, `PromptRepository`

---

### 2. [prompt-version-control](./prompt-version-control.md) - 版本控制系统  
**优先级**: P0 (核心基础)  
**估时**: 22.5小时  

**核心职责**:
- 自动版本生成（整数递增v1→v2→v3）
- 版本切换（复制机制，避免版本链断裂）
- 版本对比（文本差异高亮展示）
- 版本历史记录和查看

**关键组件**: `VersionService`, `PromptVersion Entity`, `DiffService`  
**依赖**: prompt-core-management（Prompt实体）

---

### 3. [prompt-testing-system](./prompt-testing-system.md) - 测试与预览系统
**优先级**: P0 (核心基础)  
**估时**: 30小时  

**核心职责**:
- 模板占位符自动解析
- 参数输入界面动态生成
- 模板渲染和结果预览
- 多版本测试支持
- 为AI API对接预留扩展（二期）

**关键组件**: `TestingService`, `TemplateParser`, `ParameterExtractor`  
**依赖**: prompt-core-management, prompt-version-control

---

### 4. [prompt-audit-security](./prompt-audit-security.md) - 审计日志与权限系统
**优先级**: P0 (横切关注点)  
**估时**: 28.5小时  

**核心职责**:
- 操作审计日志（不可篡改）
- 三角色权限控制（ADMIN/EDITOR/VIEWER）
- Symfony Security集成
- EasyAdmin权限适配
- 敏感信息安全保护

**关键组件**: `AuditService`, `SecurityService`, `AuditLog Entity`  
**依赖**: 被其他3个模块依赖（横切）

---

## 🔗 依赖关系图

```
prompt-core-management (基础)
       ↑
       │
prompt-version-control
       ↑
       │
prompt-testing-system

     ← prompt-audit-security → (横切所有模块)
```

## 📊 实施建议

### 实施顺序
1. **prompt-core-management** - 建立数据基础和基本CRUD
2. **prompt-audit-security** - 建立权限和日志框架  
3. **prompt-version-control** - 添加版本控制能力
4. **prompt-testing-system** - 完善测试和预览功能

### 里程碑规划
- **Week 1**: Core Management + Security Framework
- **Week 2**: Version Control + Testing System  
- **Week 3**: Integration Testing + UI Polish
- **Week 4**: Performance Optimization + Documentation

## 🎨 技术统一性

所有4个FRD都严格遵循项目架构红线：

- **架构模式**: 扁平化Service层，严禁DDD多层抽象
- **实体模型**: 贫血模型，Entity仅包含getter/setter
- **配置管理**: 环境变量`$_ENV`，严禁Configuration类
- **质量标准**: PHPStan Level 8，代码覆盖率≥90%
- **UI框架**: EasyAdminBundle统一界面

## 🚀 下一步

各FRD已完成**需求定义**阶段，建议：

1. 使用 `/feature-design` 逐个推进到**技术设计**阶段
2. 使用 `/feature-execute` 开始具体实施
3. 使用 `/feature-validate` 进行质量验收

---

**创建时间**: 2025-09-04  
**基于文档**: packages/prompt-manage-bundle/PRD.md