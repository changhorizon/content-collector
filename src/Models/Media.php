<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $host
 * @property string $url
 * @property int|null $http_code
 * @property string|null $http_content_type
 * @property int|null $content_size
 * @property string|null $content_hash
 * @property string|null $storage_path
 * @property Carbon|null $downloaded_at
 * @property string|null $last_task_id
 */
class Media extends Model
{
    protected $table = 'content_collector_media';

    protected $fillable = [
        'host',
        'url',
        'http_code',
        'http_content_type',
        'content_size',
        'content_hash',
        'storage_path',
        'downloaded_at',
        'last_task_id',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];
}
