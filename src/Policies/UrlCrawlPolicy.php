<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Policies;

use ChangHorizon\ContentCollector\Models\UrlLedger;

class UrlCrawlPolicy
{
    /**
     * 是否需要对该 URL 执行 crawl（fetch / parse）
     *
     * 语义：
     * - task 内避免重复 parse
     * - crawl ≠ persist，crawl 是过程，persist 是结果
     */
    public function shouldCrawl(
        string $taskId,
        string $host,
        array $params,
        string $url,
    ): bool {
        /**
         * ① 任务内数量限制
         */
        $max = (int) ($params['confine']['max_urls'] ?? PHP_INT_MAX);

        // UrlCrawlPolicy.php
        if (
            UrlLedger::where('task_id', $taskId)
                ->whereNotNull('fetched_at') // 👈 关键
                ->count() >= $max
        ) {
            return false;
        }

        /**
         * ② task 内已完成 parse 的 URL 不再 crawl
         */
        return ! UrlLedger::where('task_id', $taskId)
            ->where('host', $host)
            ->where('url', $url)
            ->whereNotNull('parsed_at')
            ->exists();
    }
}
