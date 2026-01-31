<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests\Feature;

use ChangHorizon\ContentCollector\Models\UrlLedger;
use ChangHorizon\ContentCollector\Models\ParsedPage;
use ChangHorizon\ContentCollector\Tests\TestCase;

class UrlLedgerTest extends TestCase
{
    public function test_url_is_not_parsed_twice_in_same_task(): void
    {
        $taskId = 'task-' . uniqid();
        $host = 'example.com';
        $url = 'https://example.com/a';

        UrlLedger::create([
            'task_id' => $taskId,
            'host' => $host,
            'url' => $url,
            'fetched_at' => now(),
            'parsed_at' => now(),
        ]);

        ParsedPage::create([
            'raw_page_id' => 1,
            'host' => $host,
            'url' => $url,
            'html_title' => 'test',
        ]);

        $exists = ParsedPage::where('host', $host)
            ->where('url', $url)
            ->count();

        $this->assertSame(1, $exists);
    }
}
