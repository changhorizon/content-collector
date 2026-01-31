<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Models;

use ChangHorizon\ContentCollector\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $task_id
 * @property string $host
 * @property TaskStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class Task extends Model
{
    protected $table = 'content_collector_tasks';

    protected $fillable = [
        'task_id',
        'host',
        'status',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function urlLedgers(): HasMany
    {
        return $this->hasMany(UrlLedger::class, 'task_id', 'task_id');
    }

}
