# 📦 Content Collector

![License](https://img.shields.io/github/license/changhorizon/content-collector?style=flat-square)
![Latest Version](https://img.shields.io/packagist/v/changhorizon/content-collector?style=flat-square)
![PHP Version](https://img.shields.io/badge/php-8.2--8.4-blue?style=flat-square)
![CI](https://github.com/changhorizon/content-collector/actions/workflows/tests.yml/badge.svg?branch=main&style=flat-square)

A safety-first Laravel crawler designed for controlled, trusted targets, with explicit task lifecycle, job idempotency
and built-in SSRF protection.

## ✨ 特性

- 多 Job 并发爬取（Fetch / Parse / Media 解耦）
- 严格 URL Policy，防 SSRF
- Task 生命周期明确（start → completed）
- Redis 可选，自动 DB 回退（行为一致）
- Job 幂等，安全可重试
- 数据库是最终一致性的真实状态源（Redis 仅作为加速器）

## 安装

```bash
composer require changhorizon/content-collector
php artisan vendor:publish --tag=content-collector-config
php artisan vendor:publish --tag=content-collector-migrations
php artisan migrate
```

> 本包依赖 Laravel Queue，请确保已配置并运行 queue worker

## 🚀 快速使用

```php
use ChangHorizon\ContentCollector\Services\Crawler;

Crawler::run([
   'confine' => [
      'max_urls' => 10000,
   ],
   'site' => [
      'entry' => 'https://example.com',
   ],
]);
```

## 🔄 爬虫流程（架构约束）

爬虫遵循严格的、以数据库为中心的流程：

1. **抓取**
    - 始终生成一个 `RawPage` 记录作为爬取事实。
    - 原始 HTML 被存储并作为唯一数据源。

2. **解析**
    - 从数据库读取原始 HTML，而不是作业有效负载。
    - 如果存在原始内容，则始终尝试解析。

3. **持久化（策略控制）**
    - `ContentPersistencePolicy` 决定是否持久化已解析的结果（例如 `ParsedPage`、`Reference`）。
    - 无论是否持久化，都会记录解析完成情况。

> RawPage 始终作为抓取与解析阶段的唯一事实源存在。

## 🧠 核心概念

Task（一次爬取）

- 每次 Crawler::run() 生成一个 task_id
- Task 的完成条件：
    - 所有 RawPage.fetched_at 均不为 null
    - 即：系统只关心「是否尝试过抓取」，而非抓取是否成功

❗ 完成 ≠ 内容 100% 成功

失败、被拒绝、404 都是真实结果，不会阻塞 Task 完成。

### Job 说明

| Job              | 职责                       |
|------------------|--------------------------|
| FetchPageJob     | HTTP 获取页面（产生 RawPage 事实） |
| ParsePageJob     | 解析 HTML，提取 links / meta  |
| DownloadMediaJob | 下载媒体资源                   |

> ⚠️ 只有 FetchPageJob 会触发 Task 完成检查

## 🔐 安全策略（重要）

### URL Policy（默认）

- ❌ 禁止 localhost / 127.0.0.1
- ❌ 禁止私有 IP
- ❌ 禁止 file:// / javascript:
- ❌ 禁止跨 host（默认）

> 所有 URL 在 Fetch 前校验

### SSRF 防护

- DNS 解析结果校验
- IP 段白 / 黑名单
- Scheme 白名单

> ⚠️ SSRF 防护为强制策略，无法通过配置完全关闭

## ⚖️ 并发与 Redis 回退

- Redis 不是必需
- Redis 不可用时：
    - 自动使用 DB 行锁
    - 性能下降，但行为一致（已测试）

> 无需额外配置。

## 🧪 测试策略

- 只测试：
    - Task 生命周期
    - Job 幂等
    - Redis 回退行为
    - URL Policy 与 Ledger 约束
- 不测试：
    - HTML 解析细节
    - Laravel Queue 行为

## ⚠️ 使用约束（必须读）

以下为设计层面的明确约束，而非未来待改进事项：

- ❌ 不适合超大规模分布式爬虫
- ❌ 不保证实时性
- ❌ 不保证媒体下载成功率
- ✅ 适合 受控、可信目标站点

## 🛠 设计原则（供维护者）

- 数据库是最终真相
- Job 必须幂等
- Redis 只能是加速器
- 失败优雅、完成明确
- 宁可少功能，不要隐性状态

### 内容持久化策略

`ContentPersistencePolicy` 仅控制是否持久化**派生内容**（已解析的页面、引用）。 它**不**影响抓取、原始存储或爬取计划。

> 解析任务不再承载原始 HTML 有效负载。
> 所有解析均基于持久化的原始页面执行。

## 📌 迁移与升级提示

- 不要修改 migration
- 如需扩展字段，使用新 migration
- Job / Task 语义不可破坏

## 📄 License

MIT License. See the [LICENSE](LICENSE) file for details.
