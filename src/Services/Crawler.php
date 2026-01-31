<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\DTO\PageContext;
use ChangHorizon\ContentCollector\Factories\HttpClientFactory;
use ChangHorizon\ContentCollector\Jobs\FetchPageJob;
use ChangHorizon\ContentCollector\Models\Task;
use ChangHorizon\ContentCollector\Models\UrlLedger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

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

        if (!$this->entryReachable($entry, $taskId)) {
            Task::where('task_id', $taskId)->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);
            return;
        }

        // 入口 URL 直接写入 ledger（统一事实源）
        UrlLedger::create([
            'task_id' => $taskId,
            'host' => $this->host,
            'url' => $entry,
            'discovered_at' => now(),
        ]);

        FetchPageJob::dispatch(
            context: new PageContext(
                taskId: $taskId,
                host: $this->host,
                params:$this->params,
                url: $entry,
                fromUrl: null,
                rawPageId: null,
            ),
        );

        Log::info("Crawler task [$taskId] started for host: {$this->host}");
    }

    protected function entryReachable(string $url, string $taskId): bool
    {
        $http = HttpClientFactory::create($this->params['client']);

        for ($i = 1; $i <= 3; $i++) {
            try {
                $response = $http->get($url);
                if ($response->getStatusCode() === 200) {
                    return true;
                }

                Log::warning("Crawler entry check failed [$url], taskId: $taskId, status: {$response->getStatusCode()}");
            } catch (Throwable $e) {
                Log::error("Crawler entry error: {$e->getMessage()}, taskId: $taskId");
            }

            usleep(1_000_000);
        }

        Log::error("Crawler: entry unreachable after 3 attempts, taskId: $taskId");
        return false;
    }
}
