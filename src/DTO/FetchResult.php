<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\DTO;

class FetchResult
{
    public function __construct(
        public bool $success,
        public ?int $statusCode = null,
        public ?string $body = null,
        public array $headers = [],
        public ?string $error = null,
    ) {
    }
}
