<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests\Feature;

use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Models\ParsedPage;
use ChangHorizon\ContentCollector\Models\Media;
use ChangHorizon\ContentCollector\Models\Reference;
use ChangHorizon\ContentCollector\Enums\ReferenceTargetType;
use ChangHorizon\ContentCollector\Tests\TestCase;

class ReferenceTest extends TestCase
{
    public function test_reference_is_written_after_media_persisted(): void
    {
        $raw = RawPage::create([
            'task_id' => 'task-1',
            'host' => 'example.com',
            'url' => 'https://example.com',
        ]);

        $parsed = ParsedPage::create([
            'raw_page_id' => $raw->id,
            'host' => $raw->host,
            'url' => $raw->url,
        ]);

        $media = Media::create([
            'task_id' => 'task-1',
            'host' => 'example.com',
            'url' => 'https://example.com/a.png',
        ]);

        Reference::create([
            'raw_page_id' => $raw->id,
            'target_id' => $media->id,
            'target_type' => ReferenceTargetType::MEDIA->value,
        ]);

        $this->assertDatabaseHas('content_collector_references', [
            'raw_page_id' => $raw->id,
            'target_id' => $media->id,
            'target_type' => ReferenceTargetType::MEDIA->value,
        ]);
    }
}
