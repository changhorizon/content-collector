<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Policies;

use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Support\UrlNormalizer;
use ChangHorizon\ContentCollector\Support\PathMatcher;

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

        /**
         * ① 已存在内容（同一 task）
         */
        if (RawPage::where('task_id', $taskId)
            ->where('host', $host)
            ->where('url', $normalized)
            ->exists()) {
            return false;
        }

        /**
         * ③ 内容规则判断
         */
        $priority = $params['site']['priority'] ?? 'black';
        $allow = $params['site']['allow'] ?? [];
        $deny = $params['site']['deny'] ?? [];

        $path = parse_url($normalized, PHP_URL_PATH) ?? '/';
        $path = strtolower('/' . ltrim(rawurldecode($path), '/'));

        if (!in_array($priority, ['black', 'white'], true)) {
            $priority = 'black';
        }

        if ($priority === 'black') {
            if ($deny && PathMatcher::matches($path, $deny)) {
                return false;
            }
            if ($allow && !PathMatcher::matches($path, $allow)) {
                return false;
            }
        } else {
            if ($allow && !PathMatcher::matches($path, $allow)) {
                return false;
            }
            if ($deny && PathMatcher::matches($path, $deny)) {
                return false;
            }
        }

        return true;
    }
}
