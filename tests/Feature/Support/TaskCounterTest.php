<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests\Feature\Support;

use ChangHorizon\ContentCollector\Support\TaskCounter;
use ChangHorizon\ContentCollector\Tests\TestCase;

class TaskCounterTest extends TestCase
{
    public function test_task_counter_increment_is_consistent(): void
    {
        $taskId = 'test-task-' . uniqid();
        $host = 'example.com';
        $prefix = 'test:counter';

        $counter = new TaskCounter(
            taskId: $taskId,
            host: $host,
            prefix: $prefix,
        );

        $base = $counter->current();

        $this->assertSame($base + 1, $counter->increment());
        $this->assertSame($base + 1, $counter->current());

        $this->assertSame($base + 2, $counter->increment());
        $this->assertSame($base + 2, $counter->current());
    }
}
