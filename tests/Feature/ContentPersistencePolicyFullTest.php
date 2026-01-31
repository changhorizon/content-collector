<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests\Feature;

use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Policies\ContentPersistencePolicy;
use ChangHorizon\ContentCollector\Tests\TestCase;

class ContentPersistencePolicyFullTest extends TestCase
{
    public function test_incremental_mode_skips_existing_raw(): void
    {
        $policy = new ContentPersistencePolicy();

        RawPage::create([
            'task_id' => 'old-task',
            'host'    => 'example.com',
            'url'     => 'https://example.com/page',
        ]);

        $this->assertFalse(
            $policy->shouldPersist(
                'new-task',
                'example.com',
                [
                    'site' => [
                        'full' => false,
                        'priority' => 'black',
                        'allow' => ['/*'],
                        'deny' => [],
                    ],
                ],
                'https://example.com/page',
            ),
        );
    }

    public function test_full_mode_allows_repersisting_existing_raw(): void
    {
        $policy = new ContentPersistencePolicy();

        RawPage::create([
            'task_id' => 'old-task',
            'host'    => 'example.com',
            'url'     => 'https://example.com/page',
        ]);

        $this->assertTrue(
            $policy->shouldPersist(
                'new-task',
                'example.com',
                [
                    'site' => [
                        'full' => true,
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
