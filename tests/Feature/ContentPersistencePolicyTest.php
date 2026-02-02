<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests\Feature;

use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Policies\ContentPersistencePolicy;
use ChangHorizon\ContentCollector\Tests\TestCase;

class ContentPersistencePolicyFullTest extends TestCase
{
    public function test_incremental_mode_allows_persist_in_new_task(): void
    {
        $policy = new ContentPersistencePolicy();

        // 旧 task 中已存在
        RawPage::create([
            'task_id' => 'old-task',
            'host'    => 'example.com',
            'url'     => 'https://example.com/page',
        ]);

        // 新 task：应允许 persist
        $this->assertTrue(
            $policy->shouldPersist(
                'new-task',
                'example.com',
                [
                    'site' => [
                        'full' => false, // incremental
                        'priority' => 'black',
                        'allow' => ['/*'],
                        'deny' => [],
                    ],
                ],
                'https://example.com/page',
            ),
        );
    }

    public function test_task_local_uniqueness_is_enforced(): void
    {
        $policy = new ContentPersistencePolicy();

        RawPage::create([
            'task_id' => 'task-1',
            'host'    => 'example.com',
            'url'     => 'https://example.com/page',
        ]);

        // 同一 task 内重复 → 必须 false
        $this->assertFalse(
            $policy->shouldPersist(
                'task-1',
                'example.com',
                [
                    'site' => [
                        'full' => true, // full 也不允许 task 内重复
                        'priority' => 'black',
                        'allow' => ['/*'],
                        'deny' => [],
                    ],
                ],
                'https://example.com/page',
            ),
        );
    }
}
