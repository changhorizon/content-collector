<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Schedulers;

use ChangHorizon\ContentCollector\Contracts\TaskCounterInterface;
use ChangHorizon\ContentCollector\DTO\PageContext;
use ChangHorizon\ContentCollector\Jobs\FetchPageJob;
use ChangHorizon\ContentCollector\Models\UrlLedger;
use ChangHorizon\ContentCollector\Policies\UrlCrawlPolicy;
use ChangHorizon\ContentCollector\Support\TaskCounter;
use ChangHorizon\ContentCollector\Support\UrlNormalizer;

class PageJobScheduler
{
    protected UrlCrawlPolicy $policy;
    protected ?TaskCounterInterface $counter = null;

    public function __construct(
        ?UrlCrawlPolicy $policy = null,
        ?TaskCounterInterface $counter = null,
    ) {
        $this->policy = $policy ?? new UrlCrawlPolicy();
        $this->counter = $counter;
    }

    public function schedule(PageContext $context, array $links): void
    {
        if ($links === []) {
            return;
        }

        $links = array_values(array_unique(array_map(
            fn (string $url) => UrlNormalizer::normalize($url),
            $links,
        )));

        // 已存在于 ledger 的 URL（本任务）
        $existing = UrlLedger::where('task_id', $context->taskId)
            ->where('host', $context->host)
            ->whereIn('url', $links)
            ->pluck('url')
            ->all();

        $existingMap = array_flip($existing);

        $maxUrls = (int) ($params['confine']['max_urls'] ?? PHP_INT_MAX);
        $counter = $this->counter($context->taskId, $context->host, $context->params);

        foreach ($links as $url) {
            if (isset($existingMap[$url])) {
                continue;
            }

            if ($counter->current() >= $maxUrls) {
                break;
            }

            if (!$this->policy->shouldCrawl($context->taskId, $context->host, $context->params, $url)) {
                UrlLedger::create([
                    'task_id' => $context->taskId,
                    'host' => $context->host,
                    'url' => $url,
                    'discovered_at' => now(),
                    'final_result' => 'denied',
                    'final_reason' => 'policy_denied',
                ]);
                continue;
            }

            // 先写 ledger，占坑（幂等核心）
            UrlLedger::create([
                'task_id' => $context->taskId,
                'host' => $context->host,
                'url' => $url,
                'discovered_at' => now(),
                'scheduled_at' => now(),
            ]);

            FetchPageJob::dispatch(
                new PageContext(
                    taskId: $context->taskId,
                    host: $context->host,
                    params: $context->params,
                    url: $url,
                    fromUrl: $context->url,
                    rawPageId: null,
                ),
            );

            $counter->increment();
        }
    }

    protected function counter(string $taskId, string $host, array $params): TaskCounterInterface
    {
        return $this->counter ?? new TaskCounter(
            taskId: $taskId,
            host: $host,
            prefix: $params['redis']['task_count_prefix'],
        );
    }
}
