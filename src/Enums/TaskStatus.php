<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Enums;

enum TaskStatus: string
{
    case RUNNING = 'running';
    case FINISHED = 'finished';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
