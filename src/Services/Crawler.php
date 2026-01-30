<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\Factories\HttpClientFactory;
use ChangHorizon\ContentCollector\Jobs\FetchPageJob;
use ChangHorizon\ContentCollector\Models\CrawlTask;
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
        $entry = $this->params['site']['entry'];
        $taskId = (string) Str::uuid();

        CrawlTask::create([
            'task_id' => $taskId,
            'host' => $this->host,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $http = HttpClientFactory::create($this->params['client']);

        $attempts = 0;
        $maxAttempts = 3;

        while ($attempts < $maxAttempts) {
            try {
                $response = $http->get($entry);

                if ($response->getStatusCode() === 200) {
                    break;
                }

                Log::warning("Crawler: entry check failed [$entry], taskId: $taskId, status: {$response->getStatusCode()}");
            } catch (Throwable $e) {
                Log::error("Crawler entry error: {$e->getMessage()}, taskId: $taskId");
            }

            $attempts++;
            if ($attempts < $maxAttempts) {
                usleep(1000_000); // 1 秒重试
            } else {
                Log::error("Crawler: entry unreachable after {$maxAttempts} attempts, taskId: $taskId");
                return;
            }
        }

        // 派发入口 CrawlPageJob
        FetchPageJob::dispatch(
            host: $this->host,
            params: $this->params,
            taskId: $taskId,
            url: $entry,
            depth: 0,
            discoveredFrom: null,
        );

        Log::info("Crawler task [$taskId] started for host: {$this->host}");
    }
}
