<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\DTO\TaskResult;
use ChangHorizon\ContentCollector\Events\TaskCompleted;
use ChangHorizon\ContentCollector\Models\CrawlTask;
use ChangHorizon\ContentCollector\Models\Media;
use ChangHorizon\ContentCollector\Models\ParsedPage;
use ChangHorizon\ContentCollector\Models\RawPage;
use DateTime;

class TaskFinalizer
{
    public static function tryFinalize(string $taskId): void
    {
        $task = CrawlTask::where('task_id', $taskId)
            ->where('status', 'running')
            ->first();

        if (!$task) {
            return;
        }

        // 是否还有未完成 RawPage
        $pending = RawPage::where('task_id', $taskId)
            ->whereNull('fetched_at')
            ->exists();

        if ($pending) {
            return;
        }

        $task->update([
            'status' => 'completed',
            'finished_at' => now(),
        ]);

        event(new TaskCompleted(
            new TaskResult(
                host: $task->host,
                parsedPages: ParsedPage::where('host', $task->host)->get(),
                media: Media::where('host', $task->host)->get(),
                startedAt: new DateTime($task->started_at),
                finishedAt: new DateTime($task->finished_at),
            ),
        ));
    }
}
