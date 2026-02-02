<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Models;

use ChangHorizon\ContentCollector\Enums\UrlLedgerResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $task_id
 * @property string $host
 * @property string $url
 * @property Carbon|null $discovered_at
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $fetched_at
 * @property Carbon|null $parsed_at
 * @property UrlLedgerResult|null $final_result
 * @property string|null $final_reason
 */
class UrlLedger extends Model
{
    protected $table = 'content_collector_url_ledger';

    protected $fillable = [
        'task_id',
        'host',
        'url',
        'discovered_at',
        'scheduled_at',
        'fetched_at',
        'parsed_at',
        'final_result',
        'final_reason',
    ];

    protected $casts = [
        'discovered_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'fetched_at' => 'datetime',
        'parsed_at' => 'datetime',
        'final_result' => UrlLedgerResult::class,
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id', 'task_id');
    }

    public static function acquireFetchLock(
        string $taskId,
        string $host,
        string $url,
    ): bool {
        return self::where('task_id', $taskId)
                ->where('host', $host)
                ->where('url', $url)
                ->whereNull('fetched_at')      // 👈 关键：只允许“没 fetch 过的”
                ->update(
                    [
                        'fetched_at' => now(),  // 👈 抢锁即占位
                    ],
                ) === 1;
    }
}
