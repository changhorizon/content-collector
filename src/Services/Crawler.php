<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\DTO\PageContext;
use ChangHorizon\ContentCollector\Jobs\FetchPageJob;
use ChangHorizon\ContentCollector\Models\Task;
use ChangHorizon\ContentCollector\Models\UrlLedger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Crawler
{
    protected string $host;
    protected array $params;

    public function __construct(string $host, array $params)
    {
        $this->host = $host;
        $this->params = $params;
    }

    public function run(): void
    {
        $taskId = (string) Str::uuid();
        $entry = $this->params['site']['entry'];

        Task::create([
            'task_id' => $taskId,
            'host' => $this->host,
            'status' => 'running',
            'started_at' => now(),
        ]);

        // 统一事实源：占入口 URL
        UrlLedger::firstOrCreate(
            [
                'task_id' => $taskId,
                'host' => $this->host,
                'url' => $entry,
            ],
            [
                'discovered_at' => now(),
                'scheduled_at' => now(),
            ],
        );

        FetchPageJob::dispatch(
            new PageContext(
                taskId: $taskId,
                host: $this->host,
                params: $this->params,
                url: $entry,
                fromUrl: null,
                rawPageId: null,
            ),
        )->onQueue($this->params['queues']['crawl']);

        Log::info("Crawler task [$taskId] started for host: {$this->host}");
    }
}
