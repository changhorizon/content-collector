<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Jobs\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

trait JobRuntimeGuard
{
    /**
     * Job 最大尝试次数
     */
    public int $tries = 3;

    /**
     * Job 超时时间（秒）
     */
    public int $timeout = 30;

    /**
     * Job 永久失败时回调
     */
    public function failed(Throwable $e): void
    {
        Log::error(static::class . ' permanently failed', [
            'job' => static::class,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * 统一执行入口
     * @throws Throwable
     */
    protected function guarded(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            Log::warning(static::class . ' failed', [
                'job' => static::class,
                'attempts' => method_exists($this, 'attempts') ? $this->attempts() : null,
                'error' => $e->getMessage(),
            ]);

            // Laravel 会根据 $tries 自动控制是否重试
            throw $e;
        }
    }
}
