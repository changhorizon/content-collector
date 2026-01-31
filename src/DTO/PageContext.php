<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\DTO;

class PageContext
{
    public function __construct(
        public string $taskId,
        public string $host,
        public array $params,
        public string $url,
        public ?string $fromUrl = null,
        public ?int $rawPageId = null,
    ) {
    }
}
