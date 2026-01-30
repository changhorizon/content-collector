# 📦 Content Collector

![License](https://img.shields.io/github/license/changhorizon/content-collector?style=flat-square)
![Latest Version](https://img.shields.io/packagist/v/changhorizon/content-collector?style=flat-square)
![PHP Version](https://img.shields.io/badge/php-8.2--8.4-blue?style=flat-square)
![CI](https://github.com/changhorizon/content-collector/actions/workflows/tests.yml/badge.svg?branch=main&style=flat-square)

A safety-first Laravel crawler with explicit task lifecycle and SSRF protection.

## ✨ 特性

- 多 Job 并发爬取（Fetch / Parse / Media 解耦）
- 严格 URL Policy，防 SSRF
- Task 生命周期明确（start → completed）
- Redis 可选，自动 DB 回退（行为一致）
- Job 幂等，安全可重试

数据库是**最终一致性的真实状态源**

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
   'site' => [
      'entry' => 'https://example.com',
   ],
   'confine' => [
      'max_depth' => 3,
      'max_urls' => 500,
   ],
]);
```

## 🧠 核心概念

Task（一次爬取）

- 每次 Crawler::run() 生成一个 task_id
- Task 完成条件：
  > 所有 RawPage.fetched_at 均不为 null

❗ 完成 ≠ 内容 100% 成功

Job 说明

| Job              | 职责                      |
|------------------|-------------------------|
| FetchPageJob     | HTTP 获取页面（RawPage）      |
| ParsePageJob     | 解析 HTML，提取 links / meta |
| DownloadMediaJob | 下载媒体资源                  |

> ⚠️ 只有 FetchPageJob 会触发 Task 完成检查

## 🔐 安全策略（重要）

URL Policy（默认）

- ❌ 禁止 localhost / 127.0.0.1
- ❌ 禁止私有 IP
- ❌ 禁止 file:// / javascript:
- ❌ 禁止跨 host（默认）

> 所有 URL 在 Fetch 前校验

SSRF 防护

- DNS 解析结果校验
- IP 段白 / 黑名单
- Scheme 白名单

## ⚖️ 并发与 Redis 回退

- Redis 不是必需
- Redis 不可用时：
    - 自动使用 DB 行锁
    - 性能下降，但行为一致（已测试）

无需额外配置。

## 🧪 测试策略

- 只测试：
    - Task 生命周期
    - Job 幂等
    - Redis 回退
    - URL Policy
- 不测试：
    - HTML 解析细节
    - Laravel Queue 行为

## ⚠️ 使用约束（必须读）

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
