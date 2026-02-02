<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $host
 * @property string $url
 * @property string|null $html_title
 * @property string|null $html_body
 * @property array|null $html_meta
 * @property Carbon|null $parsed_at
 * @property int|null $raw_page_id
 * @property string|null $last_task_id
 */
class ParsedPage extends Model
{
    protected $table = 'content_collector_parsed_pages';

    protected $fillable = [
        'host',
        'url',
        'html_title',
        'html_body',
        'html_meta',
        'parsed_at',
        'raw_page_id',
        'last_task_id',
    ];

    protected $casts = [
        'html_meta' => 'array',
        'parsed_at' => 'datetime',
    ];

    public function rawPage(): BelongsTo
    {
        return $this->belongsTo(RawPage::class, 'raw_page_id');
    }
}
