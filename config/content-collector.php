<?php

declare(strict_types=1);

return [
    // 全局爬虫调度参数
    'confine' => [
        'delay_ms' => env('CRAWLER_DELAY_MS', 1500),
        'jitter_ms' => env('CRAWLER_JITTER_MS', 500),
        'max_urls' => env('CRAWLER_MAX_URLS', 10000),
    ],

    'redis' => [
        'enabled' => env('REDIS_ENABLED', true),
        'host_key_prefix' => env('REDIS_HOST_KEY_PREFIX', 'crawler:host:'),
        'task_count_prefix' => env('REDIS_TASK_COUNT_PREFIX', 'crawler:task:'),
        'max_concurrent_per_host' => env('REDIS_MAX_CONCURRENT_PER_HOST', 3),
    ],

    'client' => [
        'http_timeout' => env('HTTP_TIMEOUT', 15),
        'user_agents' => explode(',', env(
            'USER_AGENTS',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.5993.120 Safari/537.36',
        )),
    ],

    'sites' => [
        'example.com' => [
            'entry' => env('SITE_ENTRY', 'https://example.com'),
            'full' => env('SITE_FULL', false),
            'priority' => env('SITE_PRIORITY', 'black'),
            'allow' => explode(',', env('SITE_ALLOW', '/articles/*,/news/*')),
            'deny' => explode(',', env('SITE_DENY', '/admin/*,/login')),
        ],
    ],
];
