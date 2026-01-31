<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests\Feature;

use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Policies\ContentPersistencePolicy;
use ChangHorizon\ContentCollector\Tests\TestCase;

class ContentPersistencePolicyTest extends TestCase
{
    public function test_should_not_persist_existing_raw_page_in_same_task(): void
    {
        $taskId = 'task-' . uniqid();
        $host = 'example.com';
        $url = 'https://example.com/a';

        RawPage::create([
            'task_id' => $taskId,
            'host' => $host,
            'url' => $url,
        ]);

        $policy = new ContentPersistencePolicy();

        $result = $policy->shouldPersist(
            taskId: $taskId,
            host: $host,
            params: [],
            url: $url,
        );

        $this->assertFalse($result);
    }
}
