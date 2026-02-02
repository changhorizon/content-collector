<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\DTO;

readonly class JobContext
{
    public function __construct(
        public string $taskId,
        public string $host,
        public array $params,
    ) {
    }
}
