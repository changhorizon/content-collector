<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Models;

use Illuminate\Database\Eloquent\Model;

class CrawlTask extends Model
{
    protected $table = 'content_collector_tasks';

    protected $fillable = [
        'task_id',
        'host',
        'status',      // running / completed / failed
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
