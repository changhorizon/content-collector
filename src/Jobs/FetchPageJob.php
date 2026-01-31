<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Jobs;

use ChangHorizon\ContentCollector\Contracts\PageFetcherInterface;
use ChangHorizon\ContentCollector\DTO\FetchResult;
use ChangHorizon\ContentCollector\DTO\PageContext;
use ChangHorizon\ContentCollector\Jobs\Concerns\JobRuntimeGuard;
use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Models\UrlLedger;
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

    public function __construct(
        protected PageContext $context,
    ) {
    }

    public function handle(): void
    {
        $this->guarded(function () {
            $this->markScheduled();

            // 已终结 URL 不再处理
            if ($this->isFinalized()) {
                return;
            }

            /** @var PageFetcherInterface $fetcher */
            $fetcher = app(PageFetcherInterface::class);
            if (! $fetcher instanceof PageFetcherInterface) {
                $fetcher = new HttpPageFetcher();
            }

            /** @var FetchResult $result */
            $result = $fetcher->fetch(
                $this->context->url,
                $this->context->params,
            );

            DB::transaction(function () use ($result) {
                // ① 永远持久化 RawPage（事实）
                $rawPage = $this->persistRawPage($result);

                // ② 更新 URL Ledger（事实）
                $this->persistUrlLedger();

                // ③ 派发 Parse（不传 raw_html）
                ParsePageJob::dispatch(
                    new PageContext(
                        taskId: $this->context->taskId,
                        host: $this->context->host,
                        params: $this->context->params,
                        url: $this->context->url,
                        fromUrl: $this->context->fromUrl,
                        rawPageId: $rawPage->id,
                    ),
                );
            });

            // ④ 尝试结束 Task
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

    protected function persistRawPage(FetchResult $result): RawPage
    {
        return RawPage::updateOrCreate(
            [
                'task_id' => $this->context->taskId,
                'host'    => $this->context->host,
                'url'     => $this->context->url,
            ],
            [
                'http_code'     => $result->statusCode,
                'http_headers'  => $result->headers,
                'raw_html'      => $result->body,
                'raw_html_hash' => $result->bodyHash,
                'fetched_at'    => now(),
            ],
        );
    }

    protected function persistUrlLedger(): void
    {
        UrlLedger::updateOrCreate(
            [
                'task_id' => $this->context->taskId,
                'host'    => $this->context->host,
                'url'     => $this->context->url,
            ],
            [
                'from_url'  => $this->context->fromUrl,
                'fetched_at' => now(),
            ],
        );
    }
}
