<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests\Feature;

use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Policies\ContentPersistencePolicy;
use ChangHorizon\ContentCollector\Tests\TestCase;

class ContentPersistencePolicyTest extends TestCase
{
    public function test_task_local_uniqueness_is_enforced(): void
    {
        $policy = new ContentPersistencePolicy();

        RawPage::create([
            'task_id' => 'task-1',
            'host' => 'example.com',
            'url' => 'https://example.com/page',
        ]);

        $decision = $policy->decide(
            'task-1',
            'example.com',
            [
                'site' => [
                    'priority' => 'black',
                    'allow' => ['/*'],
                    'deny' => [],
                ],
            ],
            'https://example.com/page',
        );

        $this->assertFalse($decision->shouldPersist);
    }

    public function test_new_task_can_persist_same_url(): void
    {
        $policy = new ContentPersistencePolicy();

        RawPage::create([
            'task_id' => 'old-task',
            'host' => 'example.com',
            'url' => 'https://example.com/page',
        ]);

        $decision = $policy->decide(
            'new-task',
            'example.com',
            [
                'site' => [
                    'priority' => 'black',
                    'allow' => ['/*'],
                    'deny' => [],
                ],
            ],
            'https://example.com/page',
        );

        $this->assertTrue($decision->shouldPersist);
    }
}
