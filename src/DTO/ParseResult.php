<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\DTO;

class ParseResult
{
    public function __construct(
        public bool $success,
        public ?string $title = null,
        public ?string $bodyHtml = null,
        public array $links = [],
        public array $meta = [],
        public ?string $error = null,
    ) {
    }
}
