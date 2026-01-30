<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Jobs;

use ChangHorizon\ContentCollector\Contracts\PageFetcherInterface;
use ChangHorizon\ContentCollector\DTO\FetchResult;
use ChangHorizon\ContentCollector\Jobs\Concerns\JobRuntimeGuard;
use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Services\HttpPageFetcher;
use ChangHorizon\ContentCollector\Services\TaskFinalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchPageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use JobRuntimeGuard;

    public function __construct(
        protected string $host,
        protected string $url,
        protected string $taskId,
        protected array $params,
        protected int $depth,
        protected ?string $discoveredFrom = null,
    ) {
    }

    public function handle(): void
    {
        $this->guarded(function () {
            if ($this->alreadyFetched()) {
                return;
            }

            $fetcher = app(PageFetcherInterface::class);
            if (!$fetcher instanceof PageFetcherInterface) {
                $fetcher = new HttpPageFetcher();
            }

            /** @var FetchResult $result */
            $result = $fetcher->fetch($this->url, $this->params);

            $raw = $this->persistRawPage($result);

            // ✅ 解析 & 调度都交给 ParsePageJob
            ParsePageJob::dispatch(
                rawPageId: $raw->id,
                taskId: $this->taskId,
                params: $this->params,
            );

            // ✅ Fetch 阶段只在这里尝试 finalize
            TaskFinalizer::tryFinalize($this->taskId);
        });
    }

    protected function alreadyFetched(): bool
    {
        return RawPage::where('task_id', $this->taskId)
            ->where('url', $this->url)
            ->whereNotNull('fetched_at')
            ->exists();
    }

    protected function persistRawPage(FetchResult $result): RawPage
    {
        return RawPage::updateOrCreate(
            ['host' => $this->host, 'url' => $this->url],
            [
                'task_id' => $this->taskId,
                'status' => 'fetched',
                'status_code' => $result->statusCode,
                'headers' => $result->headers,
                'body' => $result->body,
                'fetched_at' => now(),
                'discovered_from' => $this->discoveredFrom,
            ],
        );
    }
}
