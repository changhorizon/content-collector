<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Models;

use ChangHorizon\ContentCollector\Enums\ReferenceRelation;
use ChangHorizon\ContentCollector\Enums\ReferenceTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $raw_page_id
 * @property int $target_id
 * @property ReferenceTargetType $target_type
 * @property ReferenceRelation|null $relation
 * @property string|null $source_tag
 * @property string|null $source_attr
 */
class Reference extends Model
{
    protected $table = 'content_collector_references';

    protected $fillable = [
        'raw_page_id',
        'relation',
        'target_id',
        'target_type',
        'source_tag',
        'source_attr',
    ];

    protected $casts = [
        'target_type' => ReferenceTargetType::class,
        'relation'    => ReferenceRelation::class,
    ];

    public function rawPage(): BelongsTo
    {
        return $this->belongsTo(RawPage::class, 'raw_page_id');
    }

}
