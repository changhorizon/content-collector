<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\DTO;

final readonly class MediaContext extends JobContext
{
    public function __construct(
        string $taskId,
        string $host,
        array $params,
        public int $sourceParsedPageId,
    ) {
        parent::__construct($taskId, $host, $params);
    }
}
