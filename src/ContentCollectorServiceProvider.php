<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector;

use ChangHorizon\ContentCollector\Commands\Collector;
use ChangHorizon\ContentCollector\Contracts\HttpTransportInterface;
use ChangHorizon\ContentCollector\Contracts\MediaDownloaderInterface;
use ChangHorizon\ContentCollector\Contracts\PageFetcherInterface;
use ChangHorizon\ContentCollector\Contracts\PageParserInterface;
use ChangHorizon\ContentCollector\Services\AdvancedHtmlParser;
use ChangHorizon\ContentCollector\Services\MediaDownloader;
use ChangHorizon\ContentCollector\Services\PageFetcher;
use ChangHorizon\ContentCollector\Support\HttpTransport;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

class ContentCollectorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../config/content-collector.php' => config_path('content-collector.php'),
        ], 'content-collector-config');

        // 发布迁移文件
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'content-collector-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Collector::class,
            ]);
        }
    }

    public function register(): void
    {
        // 合并配置文件
        $this->mergeConfigFrom(
            __DIR__ . '/../config/content-collector.php',
            'content-collector',
        );

        // ✅ 绑定接口到默认实现
        $this->app->bind(PageParserInterface::class, function () {
            return new AdvancedHtmlParser();
        });

        $this->app->bind(HttpTransportInterface::class, function () {
            return new HttpTransport();
        });

        $this->app->bind(PageFetcherInterface::class, function ($app) {
            return new PageFetcher(
                transport: $app->make(HttpTransportInterface::class),
            );
        });

        $this->app->bind(MediaDownloaderInterface::class, function ($app) {
            return new MediaDownloader(
                transport: $app->make(HttpTransportInterface::class),
                storage: $app->get(Filesystem::class),
            );
        });
    }
}
