<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Support;

use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisAvailability
{
    /**
     * 判断 Redis 是否可用
     *
     * 规则：
     * - 能 ping 成功 → 可用
     * - 任意异常 → 不可用
     */
    public static function available(): bool
    {
        try {
            Redis::connection()->ping();
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
