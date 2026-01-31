<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests\Feature;

use ChangHorizon\ContentCollector\DTO\PageContext;
use ChangHorizon\ContentCollector\Jobs\FetchPageJob;
use ChangHorizon\ContentCollector\Jobs\ParsePageJob;
use ChangHorizon\ContentCollector\Models\RawPage;
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

        $params = [
            'site' => [
                'priority' => 'black',
                'allow' => ['/*'],
                'deny' => [],
            ],
            'confine' => [
                'max_urls' => 100,
            ],
        ];

        $context = new PageContext(
            taskId: $taskId,
            host: $host,
            params: $params,
            url: 'https://example.com',
            fromUrl: null,
            rawPageId: null,
        );

        // 同步执行 Fetch
        $job = new FetchPageJob($context);
        $job->handle();

        // RawPage 应存在（因为 shouldPersist = true）
        $this->assertDatabaseHas('content_collector_raw_pages', [
            'task_id' => $taskId,
            'host'    => $host,
            'url'     => 'https://example.com',
        ]);

        // UrlLedger 必须存在
        $this->assertDatabaseHas('content_collector_url_ledger', [
            'task_id' => $taskId,
            'host'    => $host,
            'url'     => 'https://example.com',
        ]);

        // fetched_at 已写（事实）
        $this->assertNotNull(
            UrlLedger::where('task_id', $taskId)
                ->where('url', 'https://example.com')
                ->value('fetched_at'),
        );

        // ParseJob 必须被派发
        Queue::assertPushed(ParsePageJob::class);
    }
}
