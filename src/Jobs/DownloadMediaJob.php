<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Jobs;

use ChangHorizon\ContentCollector\Enums\ReferenceRelation;
use ChangHorizon\ContentCollector\Enums\ReferenceTargetType;
use ChangHorizon\ContentCollector\Factories\HttpClientFactory;
use ChangHorizon\ContentCollector\Models\Media;
use ChangHorizon\ContentCollector\Models\ParsedPage;
use ChangHorizon\ContentCollector\Models\Reference;
use ChangHorizon\ContentCollector\Services\ConcurrencyLimiter;
use ChangHorizon\ContentCollector\Support\UrlNormalizer;
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
        protected string $host,
        protected array $params,
        protected string $taskId,
        protected int $parsedPageId,
        protected string $mediaUrl,
    ) {
    }

    public function handle(): void
    {
        $parsedPage = ParsedPage::find($this->parsedPageId);
        if (!$parsedPage) {
            return;
        }

        $normalizedUrl = UrlNormalizer::normalize($this->mediaUrl);

        // 幂等：已下载直接跳过
        if (Media::where('task_id', $this->taskId)
            ->where('host', $this->host)
            ->where('url', $normalizedUrl)
            ->exists()) {
            return;
        }

        try {
            ConcurrencyLimiter::withLock(
                $this->params,
                $this->taskId,
                fn () => $this->downloadAndPersist($parsedPage, $normalizedUrl),
            );
        } catch (Throwable $e) {
            Log::error('[DownloadMediaJob] failed', [
                'task_id' => $this->taskId,
                'media_url' => $normalizedUrl,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function downloadAndPersist(ParsedPage $parsedPage, string $normalizedUrl): void
    {
        $http = HttpClientFactory::create($this->params['client']);
        $response = $http->get($normalizedUrl, ['stream' => true]);

        if ($response->getStatusCode() !== 200) {
            return;
        }

        $path = $this->generateLocalPath($response, $normalizedUrl);

        // 写文件
        Storage::writeStream($path, $response->getBody()->detach());

        DB::transaction(function () use ($parsedPage, $normalizedUrl, $response, $path) {
            $media = Media::updateOrCreate(
                [
                    'task_id' => $this->taskId,
                    'host' => $this->host,
                    'url' => $normalizedUrl,
                ],
                [
                    'http_code' => 200,
                    'http_content_type' => $response->getHeaderLine('Content-Type'),
                    'content_size' => Storage::size($path),
                    'content_hash' => hash_file('sha256', Storage::path($path)),
                    'storage_path' => $path,
                    'downloaded_at' => now(),
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

        return "content-collector/{$this->host}/{$filename}";
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
