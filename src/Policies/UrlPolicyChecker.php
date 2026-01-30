<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Policies;

use ChangHorizon\ContentCollector\Models\RawPage;

class UrlPolicyChecker
{
    /**
     * 是否允许抓取（唯一入口）
     */
    public function shouldCrawl(string $host, array $params, string $url): bool
    {
        $normalized = $this->normalizeUrl($url);

        $full = (bool) ($params['site']['full'] ?? false);

        // 非 full 模式下，已存在直接跳过
        if (!$full && RawPage::where('host', $host)->where('url', $normalized)->exists()) {
            return false;
        }

        $priority = $params['site']['priority'] ?? 'black';
        $allow = $params['site']['allow'] ?? [];
        $deny = $params['site']['deny'] ?? [];

        $path = parse_url($normalized, PHP_URL_PATH) ?? '/';
        $path = strtolower('/' . ltrim(rawurldecode($path), '/'));

        if (!in_array($priority, ['black', 'white'], true)) {
            $priority = 'black';
        }

        if ($priority === 'black') {
            if ($deny && $this->matches($path, $deny)) {
                return false;
            }
            if ($allow && !$this->matches($path, $allow)) {
                return false;
            }
        } else {
            if ($allow && !$this->matches($path, $allow)) {
                return false;
            }
            if ($deny && $this->matches($path, $deny)) {
                return false;
            }
        }

        return true;
    }

    /**
     * URL 规范化（系统唯一标准）
     */
    public function normalizeUrl(string $url): string
    {
        $parts = parse_url(trim($url));

        if (!$parts || empty($parts['host'])) {
            return strtolower($url);
        }

        $scheme = strtolower($parts['scheme'] ?? 'http');
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        $path = '/' . ltrim($parts['path'] ?? '/', '/');

        $query = '';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            ksort($queryParams);
            $query = $queryParams ? '?' . http_build_query($queryParams) : '';
        }

        return $scheme . '://' . $host . $port . $path . $query;
    }

    /**
     * 通配符路径匹配
     */
    protected function matches(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path, FNM_CASEFOLD)) {
                return true;
            }
        }
        return false;
    }
}
