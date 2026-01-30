<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Schedulers;

use ChangHorizon\ContentCollector\Contracts\TaskCounterInterface;
use ChangHorizon\ContentCollector\Jobs\FetchPageJob;
use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Policies\UrlPolicyChecker;
use ChangHorizon\ContentCollector\Support\TaskCounter;

class PageJobScheduler
{
    protected UrlPolicyChecker $checker;
    protected ?TaskCounterInterface $counter = null;

    public function __construct(
        ?UrlPolicyChecker $checker = null,
        ?TaskCounterInterface $counter = null,
    ) {
        $this->checker = $checker ?? new UrlPolicyChecker();
        $this->counter = $counter;
    }

    /**
     * 语义化封装：用于 Job 内“派发下一层”
     */
    public function dispatchNext(
        string $host,
        array $params,
        string $taskId,
        int $currentDepth,
        string $fromUrl,
        array $links,
    ): void {
        $this->schedule(
            host: $host,
            params: $params,
            taskId: $taskId,
            depth: $currentDepth,
            from: $fromUrl,
            links: $links,
        );
    }

    /**
     * 原始调度方法（批量 links）
     */
    public function schedule(
        string $host,
        array $params,
        string $taskId,
        int $depth,
        ?string $from,
        array $links,
    ): void {
        if (empty($links)) {
            return;
        }

        $links = array_unique(array_map(
            fn (string $url) => $this->checker->normalizeUrl($url),
            $links,
        ));

        $existing = RawPage::where('host', $host)
            ->whereIn('url', $links)
            ->pluck('url')
            ->all();

        $existingMap = array_flip($existing);

        $maxUrls = (int) $params['confine']['max_urls'];
        $counter = $this->counter($taskId, $host, $params);

        foreach ($links as $link) {
            if (isset($existingMap[$link])) {
                continue;
            }

            if ($counter->current() >= $maxUrls) {
                break;
            }

            if ($this->checker->shouldCrawl($host, $params, $link)) {
                FetchPageJob::dispatch(
                    host: $host,
                    params: $params,
                    taskId: $taskId,
                    url: $link,
                    depth: $depth + 1,
                    discoveredFrom: $from,
                );

                $counter->increment();
            }
        }
    }

    protected function counter(string $taskId, string $host, array $params): TaskCounterInterface
    {
        if ($this->counter) {
            return $this->counter;
        }

        return new TaskCounter(
            taskId: $taskId,
            host: $host,
            prefix: $params['redis']['task_count_prefix'],
        );
    }
}
