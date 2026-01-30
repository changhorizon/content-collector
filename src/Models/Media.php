<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    protected $table = 'content_collector_media';

    protected $fillable = [
        'host',
        'url',
        'parsed_page_id',
        'local_path',
        'mime_type',
        'size',
        'hash',
        'downloaded_at',
    ];

    public function parsedPage(): BelongsTo
    {
        return $this->belongsTo(ParsedPage::class);
    }
}
