<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ConcurrencyLimiter
{
    public static function withLock(array $params, string $taskId, callable $callback): void
    {
        if (!self::acquire($params, $taskId)) {
            return;
        }

        try {
            $callback();
        } finally {
            self::release($params, $taskId);
        }
    }

    public static function acquire(array $params, string $taskId, int $ttl = 15): bool
    {
        return self::useRedis($params)
            ? self::acquireWithRedis($params, $taskId, $ttl)
            : self::acquireWithDb($params, $taskId, $ttl);
    }

    protected static function useRedis(array $params): bool
    {
        if (!($params['redis']['enabled'] ?? false)) {
            return false;
        }

        try {
            Redis::ping();
            return true;
        } catch (Throwable) {
            Log::warning('Redis unavailable, fallback to DB limiter');
            return false;
        }
    }

    /* ---------------- Redis ---------------- */

    protected static function acquireWithRedis(array $params, string $taskId, int $ttl): bool
    {
        $host = $params['site']['entry_host']
            ?? parse_url($params['site']['entry'], PHP_URL_HOST);

        $key = $params['redis']['host_key_prefix'] . $host . ':' . $taskId;
        $limit = (int) $params['redis']['max_concurrent_per_host'];

        $count = (int) (Redis::get($key) ?: 0);

        if ($count >= $limit) {
            return false;
        }

        Redis::multi();
        Redis::incr($key);
        Redis::expire($key, $ttl);
        Redis::exec();

        return true;
    }

    protected static function acquireWithDb(array $params, string $taskId, int $ttl): bool
    {
        $host = $params['site']['entry_host']
            ?? parse_url($params['site']['entry'], PHP_URL_HOST);

        return DB::transaction(function () use ($host, $taskId, $params) {
            $row = DB::table('content_collector_task_locks')
                ->where('host', $host)
                ->where('task_id', $taskId)
                ->lockForUpdate()
                ->first();

            $limit = (int) $params['redis']['max_concurrent_per_host'];

            if (!$row) {
                DB::table('content_collector_task_locks')->insert([
                    'host' => $host,
                    'task_id' => $taskId,
                    'count' => 1,
                    'updated_at' => now(),
                ]);
                return true;
            }

            if ($row->count >= $limit) {
                return false;
            }

            DB::table('content_collector_task_locks')
                ->where('id', $row->id)
                ->update([
                    'count' => $row->count + 1,
                    'updated_at' => now(),
                ]);

            return true;
        });
    }

    /* ---------------- DB fallback ---------------- */

    public static function release(array $params, string $taskId): void
    {
        if (self::useRedis($params)) {
            self::releaseWithRedis($params, $taskId);
        } else {
            self::releaseWithDb($params, $taskId);
        }
    }

    protected static function releaseWithRedis(array $params, string $taskId): void
    {
        $host = $params['site']['entry_host']
            ?? parse_url($params['site']['entry'], PHP_URL_HOST);

        $key = $params['redis']['host_key_prefix'] . $host . ':' . $taskId;

        Redis::decr($key);
    }

    // 向后兼容：旧 withLock API

    protected static function releaseWithDb(array $params, string $taskId): void
    {
        $host = $params['site']['entry_host']
            ?? parse_url($params['site']['entry'], PHP_URL_HOST);

        DB::table('content_collector_task_locks')
            ->where('host', $host)
            ->where('task_id', $taskId)
            ->decrement('count');
    }

    // 向后兼容：旧 releaseLock API

    public static function releaseLock(array $params, string $taskId): void
    {
        self::release($params, $taskId);
    }

}
