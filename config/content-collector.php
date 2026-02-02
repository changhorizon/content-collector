<?php

declare(strict_types=1);

$defaultUserAgents = [
    // Chrome macOS
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    // Chrome Windows
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    // Firefox Windows
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0',
];

// env → json → array（唯一事实源在 config）
$envUserAgents = env('USER_AGENTS');

$userAgents = is_string($envUserAgents)
    ? json_decode($envUserAgents, true)
    : null;

// ❗兜底：json 无效 / 非数组 / 空数组
if (!is_array($userAgents) || $userAgents === []) {
    $userAgents = $defaultUserAgents;
}

$defaultAllow = [];
$defaultDeny  = [];

$envAllow = env('SITE_ALLOW');
$envDeny  = env('SITE_DENY');

$allow = is_string($envAllow) ? json_decode($envAllow, true) : null;
$deny  = is_string($envDeny) ? json_decode($envDeny, true) : null;

// ❗兜底：json 无效 / 非数组 / 空数组
if (!is_array($allow) || $allow === []) {
    $allow = $defaultAllow;
}
if (!is_array($deny) || $deny === []) {
    $deny = $defaultDeny;
}

return [
    'redis' => [
        'enabled' => env('REDIS_ENABLED', true),
        'host_key_prefix' => env('REDIS_HOST_KEY_PREFIX', 'crawler:host:'),
        'task_count_prefix' => env('REDIS_TASK_COUNT_PREFIX', 'crawler:task:'),
        'max_concurrent_per_host' => env('REDIS_MAX_CONCURRENT_PER_HOST', 3),
    ],

    'confine' => [
        'delay_ms' => env('CRAWLER_DELAY_MS', 1500),
        'jitter_ms' => env('CRAWLER_JITTER_MS', 500),
        'max_urls' => env('CRAWLER_MAX_URLS', 10000),
    ],

    'client' => [
        'http_timeout' => env('HTTP_TIMEOUT', 15),
        // ✅ 对外保证：永远是 string[]
        'user_agents' => array_values($userAgents),
    ],

    'queues' => [
        'default' => env('QUEUE_NAME_DEFAULT', 'cc-default'),
        'crawl' => env('QUEUE_NAME_CRAWL', 'cc-crawl'),
        'parse' => env('QUEUE_NAME_PARSE', 'cc-parse'),
        'media' => env('QUEUE_NAME_MEDIA', 'cc-media'),
    ],

    'sites' => [
        'example.com' => [
            'entry' => env('SITE_ENTRY', 'https://example.com'),
            'priority' => env('SITE_PRIORITY', 'black'),
            'allow' => array_values($allow),
            'deny'  => array_values($deny),
        ],
    ],
];
