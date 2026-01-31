<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\DTO\TaskResult;
use ChangHorizon\ContentCollector\Events\TaskCompleted;
use ChangHorizon\ContentCollector\Models\Task;
use ChangHorizon\ContentCollector\Models\UrlLedger;
use ChangHorizon\ContentCollector\Models\ParsedPage;
use ChangHorizon\ContentCollector\Models\Media;

class TaskFinalizer
{
    public static function tryFinalize(string $taskId): void
    {
        $task = Task::where('task_id', $taskId)
            ->where('status', 'running')
            ->first();

        if (! $task) {
            return;
        }

        // 仍有未终结 URL（核心判定）
        $hasPending = UrlLedger::where('task_id', $taskId)
            ->whereNull('final_result')
            ->exists();

        if ($hasPending) {
            return;
        }

        $task->update([
            'status'      => 'finished',
            'finished_at' => now(),
        ]);

        event(new TaskCompleted(
            new TaskResult(
                host: $task->host,
                parsedPages: ParsedPage::where('host', $task->host)->get(),
                media: Media::where('host', $task->host)->get(),
                startedAt: $task->started_at,
                finishedAt: $task->finished_at,
            ),
        ));
    }
}
