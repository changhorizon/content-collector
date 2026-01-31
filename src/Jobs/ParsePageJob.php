<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Jobs;

use ChangHorizon\ContentCollector\Contracts\PageParserInterface;
use ChangHorizon\ContentCollector\DTO\PageContext;
use ChangHorizon\ContentCollector\DTO\ParseResult;
use ChangHorizon\ContentCollector\Enums\ReferenceRelation;
use ChangHorizon\ContentCollector\Enums\ReferenceTargetType;
use ChangHorizon\ContentCollector\Jobs\Concerns\JobRuntimeGuard;
use ChangHorizon\ContentCollector\Models\ParsedPage;
use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Models\Reference;
use ChangHorizon\ContentCollector\Models\UrlLedger;
use ChangHorizon\ContentCollector\Policies\ContentPersistencePolicy;
use ChangHorizon\ContentCollector\Schedulers\PageJobScheduler;
use ChangHorizon\ContentCollector\Services\SimpleHtmlParser;
use ChangHorizon\ContentCollector\Support\UrlNormalizer;
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

    protected PageParserInterface $parser;
    protected ContentPersistencePolicy $policy;

    public function __construct(
        protected PageContext $context,
        ?PageParserInterface $parser = null,
    ) {
        $this->parser = $parser ?? new SimpleHtmlParser();
        $this->policy = new ContentPersistencePolicy();
    }

    public function handle(): void
    {
        $this->guarded(function () {
            // 幂等：已解析直接跳过
            if ($this->alreadyParsed()) {
                return;
            }

            // ① 从 DB 读取 RawPage（唯一事实源）
            $raw = RawPage::where('task_id', $this->context->taskId)
                ->where('host', $this->context->host)
                ->where('url', $this->context->url)
                ->first();

            if (! $raw || empty($raw->raw_html)) {
                return;
            }

            // ② 解析 HTML
            $result = $this->parser->parse(
                $raw->raw_html,
                $this->context->url,
            );

            if (! $result->success) {
                return;
            }

            DB::transaction(function () use ($result, $raw) {
                // ③ Parse 阶段决定是否产生派生事实
                if ($this->policy->shouldPersist(
                    $this->context->taskId,
                    $this->context->host,
                    $this->context->params,
                    $this->context->url,
                )) {
                    $this->persistParsedPage($result, $raw);
                    $this->persistReferences($result, $raw);
                }

                // ④ 无论是否持久化，解析事实都要标记
                $this->markParsed();
            });

            // ⑤ 调度新 URL（解析的自然结果）
            (new PageJobScheduler())->schedule(
                context: $this->context,
                links: $result->links,
            );
        });
    }

    protected function alreadyParsed(): bool
    {
        return UrlLedger::where('task_id', $this->context->taskId)
            ->where('host', $this->context->host)
            ->where('url', $this->context->url)
            ->whereNotNull('parsed_at')
            ->exists();
    }

    protected function persistParsedPage(ParseResult $result, RawPage $raw): void
    {
        ParsedPage::updateOrCreate(
            [
                'raw_page_id' => $raw->id,
                'host'        => $this->context->host,
                'url'         => $this->context->url,
            ],
            [
                'html_title' => $result->title,
                'html_body'  => $result->bodyHtml,
                'html_meta'  => $result->meta,
                'parsed_at'  => now(),
            ],
        );
    }

    protected function persistReferences(ParseResult $result, RawPage $source): void
    {
        foreach (array_unique($result->links) as $link) {
            $normalized = UrlNormalizer::normalize($link);

            $target = RawPage::where('host', $this->context->host)
                ->where('url', $normalized)
                ->first();

            if (! $target) {
                continue;
            }

            Reference::firstOrCreate(
                [
                    'raw_page_id' => $source->id,
                    'target_id'   => $target->id,
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
}
