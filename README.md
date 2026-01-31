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

## 🔁 增量 vs 全量

支持 **增量抓取** 与 **全量重抓** 两种模式，通过 `site.full` 控制，该配置决定了如何处理历史的 `RawPage`。

### 配置示例

```
'site' => [
    'full' => false,
],
```

### 行为说明

#### `full = false`（默认，增量模式）

- 如果 **历史任务中已存在 RawPage（同 host + url）**
- ❌ 不再持久化 RawPage
- ✅ 仍可参与 crawl / parse（用于 link 发现）
- 适合场景：
    - 定期更新
    - 内容监控
    - 避免重复存储相同页面
    - 降低带宽和负载

#### `full = true`（全量模式）

- **忽略历史 RawPage**
- ✅ 每次任务都会重新抓取并持久化内容
- 适合场景：
    - 数据修复
    - 强制重爬
    - 结构变更后重跑
    - 内容可能频繁变化的站点

### 设计说明（给维护者）

- `full` **只影响 RawPage 持久化**
- URL 策略、任务生命周期和任务的幂等性规则依然有效
- 数据库仍是最终状态源

这个设计确保了爬取行为的明确性和可预测性。

### 一句话总结

> `full=false`：不重复存储历史内容  
> `full=true`：每次任务都是一次“全新抓取

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

## 📌 迁移与升级提示

- 不要修改 migration
- 如需扩展字段，使用新 migration
- Job / Task 语义不可破坏

## 📄 License

MIT License. See the [LICENSE](LICENSE) file for details.
