<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\DTO\TaskResult;
use ChangHorizon\ContentCollector\Events\TaskFinished;
use ChangHorizon\ContentCollector\Models\Task;
use ChangHorizon\ContentCollector\Models\UrlLedger;
use Illuminate\Support\Facades\Log;

class TaskFinalizer
{
    public function tryFinish(string $taskId): void
    {
        $hasPending = UrlLedger::where('task_id', $taskId)
            ->whereNull('final_result')
            ->exists();

        if ($hasPending) {
            return;
        }

        // 只允许 running → finished 一次
        $updated = Task::where('task_id', $taskId)
            ->where('status', 'running')
            ->update([
                'status' => 'finished',
                'finished_at' => now(),
            ]);

        if ($updated === 0) {
            // 已经结束 or 不存在，直接退出（幂等）
            return;
        }

        // 重新读取任务作为“最终事实”
        $task = Task::where('task_id', $taskId)->first();

        if (!$task) {
            return; // 理论上不会发生，防御
        }

        // ② 派发 TaskFinished 事件
        event(new TaskFinished(
            new TaskResult(
                taskId: $task->task_id,
                host: $task->host,
                status: $task->status,
                startedAt: $task->started_at,
                finishedAt: $task->finished_at,
            ),
        ));

        Log::info("Crawler task [$taskId] finished for host: {$task->host}");
    }
}
