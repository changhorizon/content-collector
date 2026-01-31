<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Support;

final class UrlNormalizer
{
    /**
     * 系统唯一 URL 规范化标准
     */
    public static function normalize(string $url): string
    {
        $parts = parse_url(trim($url));

        if (!$parts || empty($parts['host'])) {
            return strtolower($url);
        }

        $scheme = strtolower($parts['scheme'] ?? 'http');
        $host   = strtolower($parts['host']);
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';

        $path = '/' . ltrim($parts['path'] ?? '/', '/');

        $query = '';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            ksort($queryParams);
            $query = $queryParams ? '?' . http_build_query($queryParams) : '';
        }

        return $scheme . '://' . $host . $port . $path . $query;
    }
}
