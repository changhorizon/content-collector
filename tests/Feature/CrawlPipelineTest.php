<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests\Feature;

use ChangHorizon\ContentCollector\DTO\PageContext;
use ChangHorizon\ContentCollector\Enums\ReferenceTargetType;
use ChangHorizon\ContentCollector\Jobs\FetchPageJob;
use ChangHorizon\ContentCollector\Jobs\ParsePageJob;
use ChangHorizon\ContentCollector\Jobs\StoreMediaJob;
use ChangHorizon\ContentCollector\Models\Media;
use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Models\UrlLedger;
use ChangHorizon\ContentCollector\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class CrawlPipelineTest extends TestCase
{
    /**
     * 验证完整的爬虫 pipeline（包括 Media 分支）：
     *
     * 1. FetchPageJob：
     *    - 写入 RawPage
     *    - 推进 UrlLedger.fetched_at
     *    - 派发 ParsePageJob
     *
     * 2. ParsePageJob：
     *    - 写入 ParsedPage
     *    - 推进 UrlLedger.parsed_at
     *    - 派发 StoreMediaJob
     *
     * 不变式：
     * - Page 生命周期只推进一次，Media 作为子事实独立存在
     */
    public function test_complete_fetch_parse_media_pipeline(): void
    {
        /*
         |------------------------------------------------------------
         | Arrange: 设置测试所需的环境
         |------------------------------------------------------------
         */

        // 冻结队列时间：只记录 Job 的派发，不自动执行它们
        Queue::fake();

        // Fake HTTP 响应：包含页面链接和媒体链接
        Http::fake([
            '*' => Http::response(
                '<html>
                    <body>
                        <a href="/next">Next</a>
                        <img src="/images/logo.png" />
                    </body>
                </html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $taskId = 'task-' . uniqid();
        $host = 'example.com';
        $url = 'https://example.com';
        $mediaPath = '/images/logo.png';

        // 降级运行参数：关闭 redis 和 proxy 等基础设施
        $params = [
            'redis' => [
                'enabled' => false,
                'host_key_prefix' => 'crawler:host:',
                'task_count_prefix' => 'crawler:task:',
                'max_concurrent_per_host' => 3,
            ],
            'queues' => [
                'fetch' => 'cc-fetch',
                'parse' => 'cc-parse',
                'media' => 'cc-media',
            ],
            'confine' => [
                'max_urls' => 100,
            ],
            'proxy' => [
                'enabled' => false,
                'url' => 'http://localhost:3000',
                'scopes' => ['html', 'media'],
            ],
            'client' => [
                'http_timeout' => 5,
                'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64)',
            ],
            'site' => [
                'entry' => $url,
                'priority' => 'black',
                'allow' => [],
                'deny' => [],
            ],
        ];

        // 统一事实源：预创建 UrlLedger 记录
        UrlLedger::create([
            'task_id' => $taskId,
            'host' => $host,
            'url' => $url,
            'discovered_at' => now(),
            'scheduled_at' => now(),
        ]);

        $context = new PageContext(
            taskId: $taskId,
            host: $host,
            params: $params,
            url: $url,
            fromUrl: null,
            rawPageId: null,
        );

        /*
         |------------------------------------------------------------
         | Act: 执行 Fetch 阶段
         |------------------------------------------------------------
         */

        (new FetchPageJob($context))->handle();

        /*
         |------------------------------------------------------------
         | Assert: Fetch 阶段结果验证
         |------------------------------------------------------------
         */

        // ① RawPage 已写入数据库，验证抓取的页面
        $this->assertDatabaseHas('content_collector_raw_pages', [
            'host' => $host,
            'url' => $url,
            'http_code' => 200,
        ]);

        // ② UrlLedger 推进到 fetched，验证 Page 的生命周期
        $ledger = UrlLedger::where('task_id', $taskId)
            ->where('url', $url)
            ->first();

        $this->assertNotNull($ledger);
        $this->assertNotNull($ledger->fetched_at);
        $this->assertNull(
            $ledger->parsed_at,
            'Page lifecycle should not progress past fetched in Fetch stage',
        );

        // ③ ParsePageJob 被正确派发，检查队列中的任务
        Queue::assertPushed(ParsePageJob::class);

        /*
         |------------------------------------------------------------
         | Act: 执行 Parse 阶段（手动推进）
         |------------------------------------------------------------
         */

        Queue::pushed(ParsePageJob::class, function (ParsePageJob $job) {
            $job->handle();  // 手动执行 ParseJob
            return true;
        });

        /*
         |------------------------------------------------------------
         | Assert: Parse 阶段结果验证
         |------------------------------------------------------------
         */

        // ④ ParsedPage 已写入数据库，验证解析后的页面
        $this->assertDatabaseHas('content_collector_parsed_pages', [
            'host' => $host,
            'url' => $url,
            'last_task_id' => $taskId,
        ]);

        // ⑤ StoreMediaJob 被正确派发，检查媒体处理任务
        Queue::assertPushed(StoreMediaJob::class);

        /*
         |------------------------------------------------------------
         | Act: 执行 Media 阶段（手动推进）
         |------------------------------------------------------------
         */

        Queue::pushed(StoreMediaJob::class, function (StoreMediaJob $job) {
            $job->handle();  // 手动执行 MediaJob
            return true;
        });

        /*
         |------------------------------------------------------------
         | Assert: Media 阶段结果验证
         |------------------------------------------------------------
         */

        // ⑥ MediaInfo 已写入数据库，验证媒体数据存储
        $this->assertDatabaseHas('content_collector_media', [
            'host' => $host,
            'source_path' => $mediaPath,
            'last_task_id' => $taskId,
        ]);

        // 获取之前生成的 RawPage 和 Media 的 ID
        $raw = RawPage::first(); // 获取刚刚写入的 RawPage
        $media = Media::first(); // 获取刚刚写入的 Media

        // ⑦ 验证引用（Reference）表是否正确记录了 RawPage 和 Media 之间的关联
        $this->assertDatabaseHas('content_collector_references', [
            'raw_page_id' => $raw->id,
            'target_id' => $media->id,
            'target_type' => ReferenceTargetType::MEDIA->value,
        ]);

        /*
         |------------------------------------------------------------
         | 最终验证：UrlLedger 推进到 parsed（主状态机闭环）
         |------------------------------------------------------------
         |
         | 这是整个爬虫 pipeline 的终点：
         | - UrlLedger 作为唯一的状态机控制器，标志着 URL 完成了 fetch + parse 流程
         | - 当它的 parsed_at 被设置时，表示该 URL 的生命周期已完整跑完
         |
         | 注意：
         | - 在整个流程中，媒体（media）不会影响 Page 的状态，媒体处理是并行的子事实
         */
        $ledger->refresh();

        $this->assertNotNull(
            $ledger->parsed_at,
            'UrlLedger should be marked as parsed after ParseJob execution',
        );
    }
}
