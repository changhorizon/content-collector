<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\DTO;

use ChangHorizon\ContentCollector\Enums\TaskStatus;
use DateTimeInterface;

class TaskResult
{
    public string $taskId;
    public string $host;
    public TaskStatus $status;
    public DateTimeInterface $startedAt;
    public DateTimeInterface $finishedAt;

    public function __construct(
        string $taskId,
        string $host,
        TaskStatus $status,
        DateTimeInterface $startedAt,
        DateTimeInterface $finishedAt,
    ) {
        $this->taskId = $taskId;
        $this->host = $host;
        $this->status = $status;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
    }
}
