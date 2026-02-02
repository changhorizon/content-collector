<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Schedulers;

use ChangHorizon\ContentCollector\Contracts\TaskCounterInterface;
use ChangHorizon\ContentCollector\DTO\PageContext;
use ChangHorizon\ContentCollector\Models\UrlLedger;
use ChangHorizon\ContentCollector\Policies\UrlCrawlPolicy;
use ChangHorizon\ContentCollector\Support\TaskCounter;

class PageJobScheduler
{
    protected UrlCrawlPolicy $policy;
    protected ?TaskCounterInterface $counter = null;

    public function __construct(
        ?UrlCrawlPolicy $policy = null,
        ?TaskCounterInterface $counter = null,
    ) {
        $this->policy  = $policy ?? new UrlCrawlPolicy();
        $this->counter = $counter;
    }

    /**
     * @return PageContext[]
     */
    public function schedule(PageContext $context, array $links): array
    {
        if ($links === []) {
            return [];
        }

        $params  = $context->params;
        $maxUrls = (int) ($params['confine']['max_urls'] ?? PHP_INT_MAX);

        $counter = $this->counter(
            $context->taskId,
            $context->host,
            $params,
        );

        $contexts = [];

        foreach ($links as $url) {
            if ($counter->current() >= $maxUrls) {
                break;
            }

            // ledger 占坑（幂等事实）
            $ledger = UrlLedger::updateOrCreate(
                [
                    'task_id' => $context->taskId,
                    'host'    => $context->host,
                    'url'     => $url,
                ],
                [
                    'discovered_at' => now(),
                ],
            );

            // ✅ 已有最终结果的不再参与
            if ($ledger->final_result !== null) {
                continue;
            }

            // 👇 在这里盖 scheduled 章
            $ledger->update([
                'scheduled_at' => now(),
            ]);

            $contexts[] = new PageContext(
                taskId: $context->taskId,
                host: $context->host,
                params: $context->params,
                url: $url,
                fromUrl: $context->url,
                rawPageId: null,
            );

            $counter->increment();
        }

        return $contexts;
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
