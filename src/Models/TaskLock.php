<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $task_id
 * @property string $host
 * @property int $count
 */
class TaskLock extends Model
{
    protected $table = 'content_collector_task_locks';

    protected $fillable = [
        'task_id',
        'host',
        'count',
    ];
}
