<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\DTO\MediaContext;
use ChangHorizon\ContentCollector\Jobs\DownloadMediaJob;

class MediaJobDispatcher
{
    /**
     * 派发媒体下载 Job
     *
     * @param  MediaContext  $context
     * @param  array  $mediaUrls
     */
    public static function dispatch(MediaContext $context, array $mediaUrls): void
    {
        foreach ($mediaUrls as $mediaUrl) {
            DownloadMediaJob::dispatch($context, $mediaUrl);
        }
    }
}
