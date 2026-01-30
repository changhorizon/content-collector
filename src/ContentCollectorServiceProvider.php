<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector;

use ChangHorizon\ContentCollector\Commands\RunCollector;
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
                RunCollector::class,
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
    }
}
