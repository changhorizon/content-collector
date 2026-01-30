<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Contracts;

use ChangHorizon\ContentCollector\DTO\ParseResult;

interface PageParserInterface
{
    public function parse(string $html, string $baseUrl): ParseResult;
}
