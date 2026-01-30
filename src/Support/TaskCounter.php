<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Support;

use ChangHorizon\ContentCollector\Contracts\TaskCounterInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class TaskCounter implements TaskCounterInterface
{
    /**
     * 进程内兜底计数器（Redis 不可用时）
     * @var array<string,int>
     */
    protected static array $localCounters = [];
    protected string $taskId;
    protected string $host;
    protected string $key;
    protected bool $useRedis = true;

    public function __construct(
        string $taskId,
        string $host,
        string $prefix,
    ) {
        $this->taskId = $taskId;
        $this->host = $host;
        $this->key = "{$prefix}:{$host}:{$taskId}";

        $this->detectRedis();
    }

    protected function detectRedis(): void
    {
        try {
            Redis::ping();
            $this->useRedis = true;
        } catch (Throwable $e) {
            $this->useRedis = false;

            Log::warning(
                '[ContentCollector] Redis unavailable, fallback to local task counter',
                ['taskId' => $this->taskId, 'host' => $this->host],
            );
        }
    }

    /**
     * 自增并返回当前值（符合接口定义）
     */
    public function increment(): int
    {
        if ($this->useRedis) {
            try {
                return (int) Redis::incr($this->key);
            } catch (Throwable $e) {
                $this->useRedis = false;
            }
        }

        $current = $this->current() + 1;
        self::$localCounters[$this->key] = $current;

        return $current;
    }

    public function current(): int
    {
        if ($this->useRedis) {
            try {
                return (int) (Redis::get($this->key) ?? 0);
            } catch (Throwable $e) {
                $this->useRedis = false;
            }
        }

        return self::$localCounters[$this->key] ?? 0;
    }
}
