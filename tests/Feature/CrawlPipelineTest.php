<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests\Feature;

use ChangHorizon\ContentCollector\DTO\PageContext;
use ChangHorizon\ContentCollector\Jobs\FetchPageJob;
use ChangHorizon\ContentCollector\Jobs\ParsePageJob;
use ChangHorizon\ContentCollector\Models\UrlLedger;
use ChangHorizon\ContentCollector\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class CrawlPipelineTest extends TestCase
{
    public function test_fetch_parse_schedule_pipeline(): void
    {
        Queue::fake();

        Http::fake([
            'https://example.com' => Http::response(
                '<html><body><a href="/next">Next</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $taskId = 'task-' . uniqid();
        $host = 'example.com';
        $url = 'https://example.com';

        $params = [
            'site' => [
                'entry' => $url,
                'priority' => 'black',
                'allow' => ['/*'],
                'deny' => [],
            ],
            'confine' => [
                'max_urls' => 100,
            ],
            'queues' => [
                'default' => 'cc-default',
                'crawl' => 'cc-crawl',
                'parse' => 'cc-parse',
                'media' => 'cc-media',
            ],
            'client' => [
                'http_timeout' => 5,
                'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64)',
            ],
        ];

        // 👈 关键：统一事实源，ledger 先占坑
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

        // Act：同步执行 FetchJob
        (new FetchPageJob($context))->handle();

        /*
         |------------------------------------------------------------
         | Assert：Fetch 阶段事实
         |------------------------------------------------------------
         */

        // ① RawPage 已写入（唯一事实源）
        $this->assertDatabaseHas('content_collector_raw_pages', [
            'host' => $host,
            'url' => $url,
            'http_code' => 200,
        ]);

        // ② Ledger 已标记 fetched
        $ledger = UrlLedger::where('task_id', $taskId)
            ->where('url', $url)
            ->first();

        $this->assertNotNull($ledger->fetched_at);

        // ③ ParseJob 被派发（pipeline 连通性）
        Queue::assertPushed(ParsePageJob::class);
    }
}
