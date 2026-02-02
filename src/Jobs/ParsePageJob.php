<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Jobs;

use ChangHorizon\ContentCollector\Contracts\PageParserInterface;
use ChangHorizon\ContentCollector\DTO\MediaContext;
use ChangHorizon\ContentCollector\DTO\PageContext;
use ChangHorizon\ContentCollector\DTO\ParseResult;
use ChangHorizon\ContentCollector\Enums\ReferenceRelation;
use ChangHorizon\ContentCollector\Enums\ReferenceTargetType;
use ChangHorizon\ContentCollector\Enums\UrlLedgerResult;
use ChangHorizon\ContentCollector\Jobs\Concerns\JobRuntimeGuard;
use ChangHorizon\ContentCollector\Models\ParsedPage;
use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Models\Reference;
use ChangHorizon\ContentCollector\Models\UrlLedger;
use ChangHorizon\ContentCollector\Policies\ContentPersistencePolicy;
use ChangHorizon\ContentCollector\Schedulers\MediaDownloadScheduler;
use ChangHorizon\ContentCollector\Schedulers\PageJobScheduler;
use ChangHorizon\ContentCollector\Services\LinkExtractor;
use ChangHorizon\ContentCollector\Services\SimpleHtmlParser;
use ChangHorizon\ContentCollector\Services\TaskFinalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ParsePageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use JobRuntimeGuard;

    public function __construct(
        protected PageContext $context,
        protected ?PageParserInterface $parser = null,
        protected ?ContentPersistencePolicy $policy = null,
        protected ?LinkExtractor $linkExtractor = null,
        protected ?TaskFinalizer $finalizer = null,
    ) {
        $this->parser = $parser ?? new SimpleHtmlParser();
        $this->policy = $policy ?? new ContentPersistencePolicy();
        $this->linkExtractor = $linkExtractor ?? new LinkExtractor($context->host);
        $this->finalizer = $this->finalizer ?? new TaskFinalizer();
    }

    public function handle(): void
    {
        /** @var PageContext[] $nextContexts */
        $nextContexts = $this->guarded(function () {
            if ($this->alreadyParsed()) {
                return [];
            }

            // ① RawPage 是唯一事实源
            $raw = RawPage::where('task_id', $this->context->taskId)
                ->where('host', $this->context->host)
                ->where('url', $this->context->url)
                ->first();

            if (!$raw || empty($raw->raw_html)) {
                return [];
            }

            // ② parse
            $result = $this->parser->parse($raw->raw_html);

            if (!$result->success) {
                $this->markFinal(
                    UrlLedgerResult::FAILED,
                    'parse_failed',
                );
                return [];
            }

            $this->markParsed();

            $parsedPage = DB::transaction(function () use ($result, $raw) {
                if (!$this->policy->decide(
                    $this->context->taskId,
                    $this->context->host,
                    $this->context->params,
                    $this->context->url,
                )) {
                    $this->markFinal(
                        UrlLedgerResult::SKIPPED,
                        'policy_skipped',
                    );

                    return null;
                } else {
                    $parsedPage = $this->persistParsedPage($result, $raw);
                    $this->persistReferences($result, $raw);
                    $this->markFinal(
                        UrlLedgerResult::SUCCESS,
                        'parsed',
                    );

                    return $parsedPage;
                }
            });

            if ($parsedPage) {
                $mediaUrls = $this->linkExtractor->extract($result->mediaUrls, $this->context->url);
                if (!empty($mediaUrls)) {
                    $context = new MediaContext(
                        taskId: $this->context->taskId,
                        host: $this->context->host,
                        params: $this->context->params,
                        sourceParsedPageId: $parsedPage->id,
                    );
                    MediaDownloadScheduler::schedule($context, $mediaUrls);
                }
            }

            $links = $this->linkExtractor->extract($result->links, $this->context->url);

            if ($links === []) {
                return [];
            }

            // ③ 只计算“接下来要抓什么”
            return (new PageJobScheduler())->schedule(
                context: $this->context,
                links: $links,
            );
        });

        // ✅ guarded + transaction 完全结束之后，再 dispatch
        foreach ($nextContexts as $ctx) {
            FetchPageJob::dispatch($ctx)
                ->onQueue($this->context->params['queues']['crawl']);
        }

        $this->finalizer->tryFinish($this->context->taskId);
    }

    protected function alreadyParsed(): bool
    {
        return UrlLedger::where('task_id', $this->context->taskId)
            ->where('host', $this->context->host)
            ->where('url', $this->context->url)
            ->whereNotNull('final_result')
            ->exists();
    }

    protected function persistParsedPage(ParseResult $result, RawPage $raw): ParsedPage
    {
        return ParsedPage::updateOrCreate(
            [
                'raw_page_id' => $raw->id,
            ],
            [
                'host' => $this->context->host,
                'url' => $this->context->url,
                'html_title' => $result->title,
                'html_body' => $result->bodyHtml,
                'html_meta' => $result->meta,
                'parsed_at' => now(),
                'last_task_id' => $this->context->taskId,
            ],
        );
    }

    protected function persistReferences(ParseResult $result, RawPage $source): void
    {
        foreach (array_unique($result->links) as $link) {
            $target = RawPage::where('host', $this->context->host)
                ->where('url', $link)
                ->first();

            if (!$target) {
                continue;
            }

            Reference::firstOrCreate(
                [
                    'raw_page_id' => $source->id,
                    'target_id' => $target->id,
                    'target_type' => ReferenceTargetType::PAGE->value,
                ],
                [
                    'relation' => ReferenceRelation::LINK->value,
                ],
            );
        }
    }

    protected function markParsed(): void
    {
        UrlLedger::where('task_id', $this->context->taskId)
            ->where('host', $this->context->host)
            ->where('url', $this->context->url)
            ->update([
                'parsed_at' => now(),
            ]);
    }

    protected function markFinal(
        UrlLedgerResult $result,
        string $reason,
    ): void {
        UrlLedger::where('task_id', $this->context->taskId)
            ->where('host', $this->context->host)
            ->where('url', $this->context->url)
            ->whereNull('final_result') // 👈 防止重复盖章
            ->update([
                'final_result' => $result->value,
                'final_reason' => $reason,
            ]);
    }

}
