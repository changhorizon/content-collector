<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $host
 * @property string $url
 * @property string $status
 * @property string|null $content
 * @property int|null $http_code
 * @property Carbon|null $fetched_at
 * @property Carbon|null $parsed_at
 */
class RawPage extends Model
{
    protected $table = 'content_collector_raw_pages';

    protected $fillable = [
        'task_id',
        'host',
        'url',
        'depth',
        'status',
        'http_status',
        'headers',
        'raw_content',
        'discovered_from',
        'fetched_at',
        'parsed_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'first_seen_at' => 'datetime',
        'fetched_at' => 'datetime',
        'parsed_at' => 'datetime',
    ];

    /**
     * 是否已成功抓取
     */
    public function isFetched(): bool
    {
        return $this->status === 'fetched';
    }

    /**
     * 是否已解析
     */
    public function isParsed(): bool
    {
        return $this->parsed_at !== null;
    }
}
