<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests\Feature;

use ChangHorizon\ContentCollector\Models\RawPage;
use ChangHorizon\ContentCollector\Policies\ContentPersistencePolicy;
use ChangHorizon\ContentCollector\Tests\TestCase;

class ContentPersistencePolicyFullTest extends TestCase
{
    public function test_incremental_mode_skips_existing_raw_page(): void
    {
        $policy = new ContentPersistencePolicy();

        $taskId = 'task-new';
        $host   = 'example.com';
        $url    = 'https://example.com/page';

        // 历史 RawPage（不同 task）
        RawPage::create([
            'task_id'  => 'task-old',
            'host'     => $host,
            'url'      => $url,
            'raw_html' => '<html></html>',
        ]);

        $params = [
            'site' => [
                'full'     => false, // 增量模式
                'priority' => 'black',
                'allow'    => ['/*'],
                'deny'     => [],
            ],
        ];

        $this->assertFalse(
            $policy->shouldPersist($taskId, $host, $params, $url),
        );
    }

    public function test_full_mode_allows_persisting_even_if_raw_page_exists(): void
    {
        $policy = new ContentPersistencePolicy();

        $taskId = 'task-new';
        $host   = 'example.com';
        $url    = 'https://example.com/page';

        // 历史 RawPage（不同 task）
        RawPage::create([
            'task_id'  => 'task-old',
            'host'     => $host,
            'url'      => $url,
            'raw_html' => '<html></html>',
        ]);

        $params = [
            'site' => [
                'full'     => true, // 全量模式
                'priority' => 'black',
                'allow'    => ['/*'],
                'deny'     => [],
            ],
        ];

        $this->assertTrue(
            $policy->shouldPersist($taskId, $host, $params, $url),
        );
    }
}
