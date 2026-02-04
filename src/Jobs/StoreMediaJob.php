<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Jobs;

use ChangHorizon\ContentCollector\Contracts\MediaDownloaderInterface;
use ChangHorizon\ContentCollector\DTO\FetchRequest;
use ChangHorizon\ContentCollector\DTO\MediaContext;
use ChangHorizon\ContentCollector\DTO\StoredMedia;
use ChangHorizon\ContentCollector\Enums\ReferenceRelation;
use ChangHorizon\ContentCollector\Enums\ReferenceTargetType;
use ChangHorizon\ContentCollector\Models\Media;
use ChangHorizon\ContentCollector\Models\ParsedPage;
use ChangHorizon\ContentCollector\Models\Reference;
use ChangHorizon\ContentCollector\Services\ConcurrencyLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * StoreMediaJob
 *
 * 职责：
 * - 调度下载
 * - 持久化“媒体事实”
 * - 建立语义引用关系
 */
class StoreMediaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        protected MediaContext $context,
        protected string $mediaUrl,
    ) {
    }

    public function handle(): void
    {
        $parsedPage = ParsedPage::find($this->context->sourceParsedPageId);
        if (! $parsedPage) {
            return;
        }

        // 幂等：语义上已存在，直接跳过
        if (
            Media::where('host', $this->context->host)
                ->where('url', $this->mediaUrl)
                ->exists()
        ) {
            return;
        }

        try {
            ConcurrencyLimiter::withLock(
                $this->context->params,
                $this->context->taskId,
                fn () => $this->downloadAndPersist($parsedPage),
            );
        } catch (Throwable $e) {
            Log::error('[StoreMediaJob] failed', [
                'task_id'   => $this->context->taskId,
                'media_url' => $this->mediaUrl,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function downloadAndPersist(ParsedPage $parsedPage): void
    {
        $request = new FetchRequest(
            headers: array_merge(
                [
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                    'Referer' => $parsedPage->url,
                ],
                $this->context->params['client']['headers'] ?? [],
            ),
            timeout: $this->context->params['client']['timeout'] ?? null,
            proxy: in_array(
                'media',
                $this->context->params['proxy']['scopes'] ?? [],
                true,
            )
                ? $this->context->params['proxy']['url']
                : null,
        );

        /** @var MediaDownloaderInterface $downloader */
        $downloader = app(MediaDownloaderInterface::class);

        // 只提供“基准路径”，不关心扩展名
        $basePath = $this->generateBaseStoragePath($this->mediaUrl);

        /** @var StoredMedia $stored */
        $stored = $downloader->download(
            url: $this->mediaUrl,
            basePath: $basePath,
            request: $request,
        );

        DB::transaction(function () use ($parsedPage, $stored) {
            $media = Media::updateOrCreate(
                [
                    'host' => $this->context->host,
                    'url'  => $this->mediaUrl,
                ],
                [
                    'source_path' => parse_url($this->mediaUrl, PHP_URL_PATH),
                    'source_filename' => basename(
                        parse_url($this->mediaUrl, PHP_URL_PATH) ?? '',
                    ),
                    'source_query' => parse_url($this->mediaUrl, PHP_URL_QUERY),

                    // —— 技术事实（来自 Downloader）——
                    'http_status_code'  => $stored->httpStatus,
                    'http_content_type' => $stored->contentType,
                    'content_size'      => $stored->bytes,
                    'content_hash'      => $stored->hash,
                    'storage_path'      => $stored->path,
                    'stored_at'         => now(),
                    'last_task_id'      => $this->context->taskId,
                ],
            );

            Reference::firstOrCreate(
                [
                    'raw_page_id' => $parsedPage->raw_page_id,
                    'target_id'   => $media->id,
                    'target_type' => ReferenceTargetType::MEDIA->value,
                ],
                [
                    'relation' => ReferenceRelation::EMBED->value,
                ],
            );
        });
    }

    protected function generateBaseStoragePath(string $url): string
    {
        return sprintf(
            'content-collector/%s/%s',
            $this->context->host,
            hash('sha256', $url),
        );
    }
}
