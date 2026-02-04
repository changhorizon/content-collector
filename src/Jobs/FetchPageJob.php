<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Jobs;

use ChangHorizon\ContentCollector\Contracts\PageFetcherInterface;
use ChangHorizon\ContentCollector\DTO\FetchRequest;
use ChangHorizon\ContentCollector\DTO\FetchResult;
use ChangHorizon\ContentCollector\DTO\PageContext;
use ChangHorizon\ContentCollector\Enums\UrlLedgerResult;
use ChangHorizon\ContentCollector\Jobs\Concerns\JobRuntimeGuard;
use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Models\UrlLedger;
use ChangHorizon\ContentCollector\Policies\UrlCrawlPolicy;
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

    protected PageFetcherInterface $fetcher;

    public function __construct(
        protected PageContext $context,
    ) {
        $this->fetcher = app(PageFetcherInterface::class);
    }

    public function handle(): void
    {
        $parseContext = $this->guarded(function () {
            $this->markScheduled();

            if (!UrlLedger::acquireFetchLock(
                $this->context->taskId,
                $this->context->host,
                $this->context->url,
            )) {
                // 👈 没抢到 fetch 权，说明别的 job 已经在干了
                return null;
            }

            $isEntry = $this->context->fromUrl === null;
            $crawlPolicy = new UrlCrawlPolicy();

            if (!$isEntry && !$crawlPolicy->shouldCrawl(
                $this->context->taskId,
                $this->context->host,
                $this->context->params,
                $this->context->url,
            )) {
                UrlLedger::where('task_id', $this->context->taskId)
                    ->where('host', $this->context->host)
                    ->where('url', $this->context->url)
                    ->update([
                        'final_result' => UrlLedgerResult::SKIPPED->value,
                        'final_reason' => 'crawl_policy_skipped',
                    ]);

                return null;
            }

            $fetchRequest = new FetchRequest(
                headers: $this->context->params['client']['headers'] ?? [],
                timeout: $this->context->params['client']['timeout'] ?? null,
                proxy: in_array('html', $this->context->params['proxy']['scopes'], true)
                    ? $this->context->params['proxy']['url']
                    : null,
            );

            $result = $this->fetcher->fetch(
                $this->context->url,
                $fetchRequest,
            );

            if (!$result->success) {
                UrlLedger::where('task_id', $this->context->taskId)
                    ->where('url', $this->context->url)
                    ->update([
                        'final_result' => UrlLedgerResult::FAILED->value,
                        'final_reason' => 'fetch_failed',
                    ]);
                return null;
            }

            if (!$result->isHtml()) {
                UrlLedger::where('task_id', $this->context->taskId)
                    ->where('host', $this->context->host)
                    ->where('url', $this->context->url)
                    ->update([
                        'final_result' => UrlLedgerResult::SKIPPED->value,
                        'final_reason' => 'non_html_content',
                        'fetched_at' => now(),
                    ]);

                return null;
            }

            $parseContext = null;

            DB::transaction(function () use ($result, &$parseContext) {
                // ① 事实
                $rawPage = $this->persistRawPage($result);

                // ② 事实
                $this->persistUrlLedger();

                // ③ 只构造上下文，不 dispatch
                $parseContext = new PageContext(
                    taskId: $this->context->taskId,
                    host: $this->context->host,
                    params: $this->context->params,
                    url: $this->context->url,
                    fromUrl: $this->context->fromUrl,
                    rawPageId: $rawPage->id,
                );
            });

            return $parseContext;
        });

        if ($parseContext) {
            ParsePageJob::dispatch($parseContext)
                ->onQueue($this->context->params['queues']['parse']);
        }
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
        return RawPage::create([
            'task_id' => $this->context->taskId,
            'host' => $this->context->host,
            'url' => $this->context->url,
            'http_code' => $result->statusCode,
            'http_headers' => json_encode($result->headers),
            'raw_html' => $result->body,
            'raw_html_hash' => $result->bodyHash,
            'fetched_at' => now(),
        ]);
    }

    protected function persistUrlLedger(): void
    {
        UrlLedger::where('task_id', $this->context->taskId)
            ->where('host', $this->context->host)
            ->where('url', $this->context->url)
            ->update([
                'fetched_at' => now(),
            ]);
    }
}
