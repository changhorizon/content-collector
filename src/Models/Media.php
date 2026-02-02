<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $host
 * @property string $url
 * @property string|null $source_path
 * @property string|null $source_filename
 * @property string|null $source_query
 * @property int|null $http_status_code
 * @property string|null $http_content_type
 * @property int|null $content_size
 * @property string|null $content_hash
 * @property string|null $storage_path
 * @property Carbon|null $stored_at
 * @property string|null $last_task_id
 */
class Media extends Model
{
    protected $table = 'content_collector_media';

    protected $fillable = [
        'host',
        'url',
        'source_path',
        'source_filename',
        'source_query',
        'http_status_code',
        'http_content_type',
        'content_size',
        'content_hash',
        'storage_path',
        'stored_at',
        'last_task_id',
    ];

    protected $casts = [
        'stored_at' => 'datetime',
    ];
}
