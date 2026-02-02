<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Support;

final class UrlNormalizer
{
    public static function normalize(string $raw, string $baseUrl): ?string
    {
        $raw = trim($raw);

        if ($raw === '' || $raw === '#') {
            return null;
        }

        if (preg_match('#^(javascript|mailto|tel):#i', $raw)) {
            return null;
        }

        try {
            $uri = \GuzzleHttp\Psr7\UriResolver::resolve(
                new \GuzzleHttp\Psr7\Uri($baseUrl),
                new \GuzzleHttp\Psr7\Uri($raw),
            );
        } catch (\Throwable) {
            return null;
        }

        if (! in_array($uri->getScheme(), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($uri->getHost());
        if ($host === '') {
            return null;
        }

        // 默认端口去重
        $port = $uri->getPort();
        if (
            ($uri->getScheme() === 'http' && $port === 80) ||
            ($uri->getScheme() === 'https' && $port === 443)
        ) {
            $port = null;
        }

        // path normalize + 连续重复段消除
        $segments = explode('/', trim($uri->getPath(), '/'));
        $clean = [];
        foreach ($segments as $seg) {
            if ($seg !== '' && end($clean) !== $seg) {
                $clean[] = $seg;
            }
        }
        $path = $clean ? '/' . implode('/', $clean) : '';

        // query 排序
        $query = '';
        parse_str($uri->getQuery(), $params);
        if ($params) {
            ksort($params);
            $query = '?' . http_build_query($params);
        }

        return $uri->getScheme()
            . '://' . $host
            . ($port ? ':' . $port : '')
            . $path
            . $query;
    }
}
