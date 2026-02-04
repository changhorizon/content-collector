<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Schedulers;

use ChangHorizon\ContentCollector\DTO\MediaContext;
use ChangHorizon\ContentCollector\Jobs\StoreMediaJob;

class MediaDownloadScheduler
{
    /**
     * 派发媒体下载 Job
     *
     * @param  MediaContext  $context
     * @param  array  $mediaUrls
     */
    public static function schedule(MediaContext $context, array $mediaUrls): void
    {
        foreach ($mediaUrls as $mediaUrl) {
            StoreMediaJob::dispatch($context, $mediaUrl)
                ->onQueue($context->params['queues']['media']);
        }
    }
}
