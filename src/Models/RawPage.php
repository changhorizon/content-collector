<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $task_id
 * @property string $host
 * @property string $url
 * @property int|null $http_code
 * @property array|null $http_headers
 * @property string|null $raw_html
 * @property string|null $raw_html_hash
 * @property Carbon|null $fetched_at
 */
class RawPage extends Model
{
    protected $table = 'content_collector_raw_pages';

    protected $fillable = [
        'task_id',
        'host',
        'url',
        'http_code',
        'http_headers',
        'raw_html',
        'raw_html_hash',
        'fetched_at',
    ];

    protected $casts = [
        'http_headers' => 'array',
        'fetched_at' => 'datetime',
    ];

    public function parsedPage(): HasOne
    {
        return $this->hasOne(ParsedPage::class, 'raw_page_id');
    }

    public function references(): HasMany
    {
        return $this->hasMany(Reference::class, 'raw_page_id');
    }
}
