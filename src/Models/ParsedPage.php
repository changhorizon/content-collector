<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $host
 * @property string $url
 * @property string|null $title
 * @property string|null $body_html
 * @property array|null $meta_json
 * @property Carbon|null $parsed_at
 */
class ParsedPage extends Model
{
    protected $table = 'content_collector_parsed_pages';

    protected $fillable = [
        'host',
        'url',
        'title',
        'body_html',
        'meta_json',
        'parsed_at',
    ];

    protected $casts = [
        'meta_json' => 'array',
        'parsed_at' => 'datetime',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'parsed_page_id');
    }
}
