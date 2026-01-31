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

    public function __construct(
        protected PageContext $context,
        protected string $rawHtml,
        ?PageParserInterface $parser = null,
    ) {
        $this->parser = $parser ?? new SimpleHtmlParser();
    }

    public function handle(): void
    {
        $this->guarded(function () {
            // 幂等：已解析则跳过
            if ($this->alreadyParsed()) {
                return;
            }

            $result = $this->parser->parse(
                $this->rawHtml,
                $this->context->url,
            );

            if (! $result->success) {
                return;
            }

            DB::transaction(function () use ($result) {
                if ($this->context->rawPageId) {
                    $this->persistParsedPage($result);
                    $this->persistReferences($result);
                }

                $this->markParsed();
            });

            // 统一调度新 URL
            (new PageJobScheduler())->schedule(context: $this->context, links: $result->links);
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

    protected function persistParsedPage(ParseResult $result): void
    {
        ParsedPage::create([
            'raw_page_id' => $this->context->rawPageId,
            'host'        => $this->context->host,
            'url'         => $this->context->url,
            'html_title'  => $result->title,
            'html_body'   => $result->bodyHtml,
            'html_meta'   => $result->meta,
            'parsed_at'   => now(),
        ]);
    }

    protected function persistReferences(ParseResult $result): void
    {
        $sourceRawPageId = $this->context->rawPageId;

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
                    'raw_page_id' => $sourceRawPageId,
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
