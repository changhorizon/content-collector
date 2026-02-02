<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Jobs;

use ChangHorizon\ContentCollector\DTO\MediaContext;
use ChangHorizon\ContentCollector\Enums\ReferenceRelation;
use ChangHorizon\ContentCollector\Enums\ReferenceTargetType;
use ChangHorizon\ContentCollector\Factories\HttpClientFactory;
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
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class DownloadMediaJob implements ShouldQueue
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

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $parsedPage = ParsedPage::find($this->context->sourceParsedPageId);
        if (!$parsedPage) {
            // ParsedPage 不存在，说明该 MediaJob 已失去语义来源，安全退出
            return;
        }

        // 幂等：已下载直接跳过
        if (Media::where('host', $this->context->host)
            ->where('url', $this->mediaUrl)
            ->exists()) {
            return;
        }

        try {
            ConcurrencyLimiter::withLock(
                $this->context->params,
                $this->context->taskId,
                fn () => $this->downloadAndPersist($parsedPage),
            );
        } catch (Throwable $e) {
            Log::error('[DownloadMediaJob] failed', [
                'last_task_id' => $this->context->taskId,
                'media_url' => $this->mediaUrl,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function downloadAndPersist(ParsedPage $parsedPage): void
    {
        $http = HttpClientFactory::create($this->context->params['client']);
        $response = $http->get($this->mediaUrl, ['stream' => true]);

        if ($response->getStatusCode() !== 200) {
            return;
        }

        $path = $this->generateLocalPath($response, $this->mediaUrl);

        // 写文件
        Storage::writeStream($path, $response->getBody()->detach());

        DB::transaction(function () use ($parsedPage, $response, $path) {
            $media = Media::updateOrCreate(
                [
                    'host' => $this->context->host,
                    'url' => $this->mediaUrl,
                ],
                [
                    'source_path' => parse_url($this->mediaUrl, PHP_URL_PATH),
                    'source_filename' => basename(parse_url($this->mediaUrl, PHP_URL_PATH) ?? ''),
                    'source_query' => parse_url($this->mediaUrl, PHP_URL_QUERY),
                    'http_status_code' => 200,
                    'http_content_type' => $response->getHeaderLine('Content-Type'),
                    'content_size' => Storage::size($path),
                    'content_hash' => hash_file('sha256', Storage::path($path)),
                    'storage_path' => $path,
                    'stored_at' => now(),
                    'last_task_id' => $this->context->taskId,
                ],
            );

            // 写事实引用关系（raw_page -> media）
            Reference::firstOrCreate(
                [
                    'raw_page_id' => $parsedPage->raw_page_id,
                    'target_id' => $media->id,
                    'target_type' => ReferenceTargetType::MEDIA->value,
                ],
                [
                    'relation' => ReferenceRelation::EMBED->value,
                ],
            );
        });
    }

    protected function generateLocalPath(ResponseInterface $response, string $url): string
    {
        $ext = $this->detectExtension($response, $url);
        $filename = uniqid(md5($url) . '_', true) . ($ext ? '.' . $ext : '');

        return "content-collector/{$this->context->host}/{$filename}";
    }

    protected function detectExtension(ResponseInterface $response, string $url): ?string
    {
        $type = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));

        return match ($type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => strtolower(
                pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION),
            ) ?: null,
        };
    }

}
