<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\Jobs\DownloadMediaJob;

class MediaJobDispatcher
{
    /**
     * 派发媒体下载 Job
     *
     * @param  string  $host
     * @param  array  $params
     * @param  string  $taskId
     * @param  int  $parsedPageId
     * @param  array  $mediaUrls
     */
    public static function dispatch(
        string $host,
        array $params,
        string $taskId,
        int $parsedPageId,
        array $mediaUrls,
    ): void {
        foreach ($mediaUrls as $mediaUrl) {
            DownloadMediaJob::dispatch($host, $params, $taskId, $parsedPageId, $mediaUrl);
        }
    }
}
