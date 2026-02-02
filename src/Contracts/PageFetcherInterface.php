<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Contracts;

use ChangHorizon\ContentCollector\DTO\FetchRequest;
use ChangHorizon\ContentCollector\DTO\FetchResult;

interface PageFetcherInterface
{
    public function fetch(string $url, FetchRequest $request): FetchResult;
}
