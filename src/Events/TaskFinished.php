<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Events;

use ChangHorizon\ContentCollector\DTO\TaskResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskFinished
{
    use Dispatchable;
    use SerializesModels;

    public TaskResult $result;

    public function __construct(TaskResult $result)
    {
        $this->result = $result;
    }
}
