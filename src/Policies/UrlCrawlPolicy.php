<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Policies;

use ChangHorizon\ContentCollector\Models\UrlLedger;

class UrlCrawlPolicy
{
    /**
     * 是否需要对该 URL 执行 crawl（scan / parse）
     *
     * 语义：在同一 task 中，避免重复扫描页面
     */
    public function shouldCrawl(string $taskId, string $host, array $params, string $url): bool
    {
        /**
         * ② 超过本任务最大内容数量
         */
        $max = (int) ($params['confine']['max_urls'] ?? PHP_INT_MAX);

        if (UrlLedger::where('task_id', $taskId)->count() >= $max) {
            return false;
        }

        return ! UrlLedger::where('task_id', $taskId)
            ->where('host', $host)
            ->where('url', $url)
            ->whereNotNull('parsed_at')
            ->exists();
    }
}
