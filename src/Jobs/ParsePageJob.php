<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Jobs;

use ChangHorizon\ContentCollector\Contracts\PageParserInterface;
use ChangHorizon\ContentCollector\Jobs\Concerns\JobRuntimeGuard;
use ChangHorizon\ContentCollector\Models\ParsedPage;
use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Schedulers\PageJobScheduler;
use ChangHorizon\ContentCollector\Services\SimpleHtmlParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ParsePageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use JobRuntimeGuard;

    protected PageParserInterface $parser;

    public function __construct(
        protected int $rawPageId,
        protected string $taskId,
        protected array $params,
        ?PageParserInterface $parser = null,
    ) {
        $this->parser = $parser ?? new SimpleHtmlParser();
    }

    public function handle(): void
    {
        $this->guarded(function () {
            $raw = RawPage::find($this->rawPageId);

            if (!$raw || !$raw->raw_content) {
                return;
            }

            // 幂等：已解析直接跳过
            if (ParsedPage::where('host', $raw->host)->where('url', $raw->url)->exists()) {
                return;
            }

            $result = $this->parser->parse($raw->raw_content, $raw->url);

            if (!$result->success) {
                return;
            }

            ParsedPage::create([
                'host' => $raw->host,
                'url' => $raw->url,
                'title' => $result->title,
                'body_html' => $result->bodyHtml,
                'meta_json' => $result->meta,
                'parsed_at' => now(),
            ]);

            $raw->update([
                'parsed_at' => now(),
            ]);

            // ✅ 唯一调度入口
            $scheduler = new PageJobScheduler();

            $scheduler->schedule(
                host: $raw->host,
                params: $this->params,
                taskId: $this->taskId,
                depth: $raw->depth,
                from: $raw->url,
                links: $result->links,
            );
        });
    }
}
