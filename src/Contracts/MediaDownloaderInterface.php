<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Contracts;

use ChangHorizon\ContentCollector\DTO\FetchRequest;
use ChangHorizon\ContentCollector\DTO\StoredMedia;

interface MediaDownloaderInterface
{
    public function download(string $url, string $basePath, FetchRequest $request): StoredMedia;
}
