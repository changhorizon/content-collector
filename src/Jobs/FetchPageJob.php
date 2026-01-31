<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Jobs;

use ChangHorizon\ContentCollector\Contracts\PageFetcherInterface;
use ChangHorizon\ContentCollector\DTO\FetchResult;
use ChangHorizon\ContentCollector\DTO\PageContext;
use ChangHorizon\ContentCollector\Jobs\Concerns\JobRuntimeGuard;
use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Models\UrlLedger;
use ChangHorizon\ContentCollector\Policies\ContentPersistencePolicy;
use ChangHorizon\ContentCollector\Services\HttpPageFetcher;
use ChangHorizon\ContentCollector\Services\TaskFinalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class FetchPageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use JobRuntimeGuard;

    protected ContentPersistencePolicy $policy;

    public function __construct(protected PageContext $context)
    {
        $this->policy = new ContentPersistencePolicy();
    }

    public function handle(): void
    {
        $this->guarded(function () {
            $this->markScheduled();

            // 已终结 URL 不再处理
            if ($this->isFinalized()) {
                return;
            }

            $fetcher = app(PageFetcherInterface::class);
            if (!$fetcher instanceof PageFetcherInterface) {
                $fetcher = new HttpPageFetcher();
            }

            /** @var FetchResult $result */
            $result = $fetcher->fetch($this->context->url, $this->context->params);

            // 标记 fetched_at（事实）
            $this->markFetched();

            $rawPage = null;
            // 条件写 raw_page
            if ($this->policy->shouldPersist($this->context->taskId, $this->context->host, $this->context->params, $this->context->url)) {
                DB::transaction(function () use ($result, &$rawPage) {
                    $rawPage = $this->persistRawPage($result);
                    $this->persisUrlLedger();
                });
            }

            if ($result->success && !empty($result->body)) {
                // 无条件进入 Parse 阶段
                ParsePageJob::dispatch(
                    context: new PageContext(
                        taskId: $this->context->taskId,
                        host: $this->context->host,
                        params: $this->context->params,
                        url: $this->context->url,
                        fromUrl: $this->context->fromUrl,
                        rawPageId: $rawPage?->id,
                    ),
                    rawHtml: $result->body, // 解析需要//不能查数据库，因为有可能没有存储
                );
            }

            TaskFinalizer::tryFinalize($this->context->taskId);
        });
    }

    protected function isFinalized(): bool
    {
        return UrlLedger::where('task_id', $this->context->taskId)
            ->where('host', $this->context->host)
            ->where('url', $this->context->url)
            ->whereNotNull('final_result')
            ->exists();
    }

    protected function markScheduled(): void
    {
        UrlLedger::where('task_id', $this->context->taskId)
            ->where('host', $this->context->host)
            ->where('url', $this->context->url)
            ->whereNull('scheduled_at')
            ->update([
                'scheduled_at' => now(),
            ]);
    }

    protected function markFetched(): void
    {
        UrlLedger::where('task_id', $this->context->taskId)
            ->where('host', $this->context->host)
            ->where('url', $this->context->url)
            ->update([
                'fetched_at' => now(),
            ]);
    }

    protected function persistRawPage(FetchResult $result): ?RawPage
    {
        return RawPage::updateOrCreate(
            [
                'task_id' => $this->context->taskId,
                'host' => $this->context->host,
                'url' => $this->context->url,
            ],
            [
                'http_code' => $result->statusCode,
                'http_headers' => $result->headers,
                'raw_html' => $result->body,
                'raw_html_hash' => $result->bodyHash,
                'fetched_at' => now(),
            ],
        );
    }

    protected function persisUrlLedger(): UrlLedger
    {
        return UrlLedger::updateOrCreate(
            [
                'task_id' => $this->context->taskId,
                'host' => $this->context->host,
                'url' => $this->context->url,
            ],
            [
                'from_url' => $this->context->fromUrl,
                'fetched_at' => now(),
            ],
        );
    }
}
