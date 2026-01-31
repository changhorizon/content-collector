<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Policies;

use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Support\PathMatcher;
use ChangHorizon\ContentCollector\Support\UrlNormalizer;

class ContentPersistencePolicy
{
    /**
     * 是否允许持久化该 URL 的内容
     */
    public function shouldPersist(
        string $taskId,
        string $host,
        array $params,
        string $url,
    ): bool {
        $normalized = UrlNormalizer::normalize($url);
        $full = (bool) ($params['site']['full'] ?? false);

        /**
         * ① task 内唯一（永远成立）
         */
        if (RawPage::where('task_id', $taskId)
            ->where('host', $host)
            ->where('url', $normalized)
            ->exists()) {
            return false;
        }

        /**
         * ② 增量模式：历史已存在则跳过
         */
        if (! $full) {
            if (RawPage::where('host', $host)
                ->where('url', $normalized)
                ->exists()) {
                return false;
            }
        }

        /**
         * ③ 内容路径规则
         */
        $priority = $params['site']['priority'] ?? 'black';
        $allow = $params['site']['allow'] ?? [];
        $deny = $params['site']['deny'] ?? [];

        $path = parse_url($normalized, PHP_URL_PATH) ?? '/';
        $path = strtolower('/' . ltrim(rawurldecode($path), '/'));

        if (! in_array($priority, ['black', 'white'], true)) {
            $priority = 'black';
        }

        if ($priority === 'black') {
            if ($deny && PathMatcher::matches($path, $deny)) {
                return false;
            }
            if ($allow && ! PathMatcher::matches($path, $allow)) {
                return false;
            }
        } else {
            if ($allow && ! PathMatcher::matches($path, $allow)) {
                return false;
            }
            if ($deny && PathMatcher::matches($path, $deny)) {
                return false;
            }
        }

        return true;
    }
}
