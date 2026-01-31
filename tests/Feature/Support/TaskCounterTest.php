<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests\Feature\Support;

use ChangHorizon\ContentCollector\Support\TaskCounter;
use ChangHorizon\ContentCollector\Tests\TestCase;
use Illuminate\Support\Facades\Redis;

class TaskCounterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Redis::shouldReceive('connection')
            ->andReturnSelf();

        Redis::shouldReceive('get')
            ->andReturn(null);

        Redis::shouldReceive('incr')
            ->andReturnUsing(fn () => 1);

        Redis::shouldReceive('del')
            ->andReturn(1);
    }

    public function test_task_counter_increment_is_consistent(): void
    {
        $taskId = 'test-task-' . uniqid();
        $host = 'example.com';
        $prefix = 'test:counter:';

        Redis::del("{$prefix}{$host}:{$taskId}");

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
