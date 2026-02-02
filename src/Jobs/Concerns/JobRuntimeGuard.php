<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Jobs\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

trait JobRuntimeGuard
{
    public int $tries = 3;
    public int $timeout = 30;

    public function failed(Throwable $e): void
    {
        Log::error(static::class . ' permanently failed', [
            'job'   => static::class,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * 统一 job 执行边界
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     *
     * @throws Throwable
     */
    protected function guarded(callable $callback)
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            Log::warning(static::class . ' failed', [
                'job'      => static::class,
                'attempts' => method_exists($this, 'attempts') ? $this->attempts() : null,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
