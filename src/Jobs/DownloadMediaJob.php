<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Jobs;

use ChangHorizon\ContentCollector\Factories\HttpClientFactory;
use ChangHorizon\ContentCollector\Models\Media;
use ChangHorizon\ContentCollector\Models\ParsedPage;
use ChangHorizon\ContentCollector\Services\ConcurrencyLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DownloadMediaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected string $host;
    protected array $params;
    protected string $taskId;
    protected int $parsedPageId;
    protected string $mediaUrl;

    public function __construct(
        string $host,
        array $params,
        string $taskId,
        int $parsedPageId,
        string $mediaUrl,
    ) {
        $this->host = $host;
        $this->params = $params;
        $this->taskId = $taskId;
        $this->parsedPageId = $parsedPageId;
        $this->mediaUrl = $mediaUrl;
    }

    public function handle(): void
    {
        $parsedPage = ParsedPage::find($this->parsedPageId);
        if (!$parsedPage) {
            Log::warning('[DownloadMediaJob] ParsedPage not found', [
                'host' => $this->host,
                'parsed_page_id' => $this->parsedPageId,
                'media_url' => $this->mediaUrl,
            ]);
            return;
        }

        try {
            ConcurrencyLimiter::withLock(
                $this->params,
                $this->taskId,
                function () use ($parsedPage) {
                    $this->downloadMedia($parsedPage);
                },
            );
        } catch (Throwable $e) {
            Log::error('[DownloadMediaJob] Unhandled exception', [
                'host' => $this->host,
                'task_id' => $this->taskId,
                'parsed_page_id' => $this->parsedPageId,
                'media_url' => $this->mediaUrl,
                'exception' => $e->getMessage(),
            ]);

            // 交给队列系统处理失败 / 重试
            throw $e;
        }
    }

    protected function downloadMedia(ParsedPage $parsedPage): void
    {
        $http = HttpClientFactory::create($this->params['client']);
        $response = $http->get($this->mediaUrl, ['stream' => true]);

        if ($response->getStatusCode() !== 200) {
            Log::warning('[DownloadMediaJob] Media request failed', [
                'host' => $this->host,
                'media_url' => $this->mediaUrl,
                'status' => $response->getStatusCode(),
            ]);
            return;
        }

        $path = $this->generateLocalPath();
        $contentLength = (int) $response->getHeaderLine('Content-Length');

        if ($contentLength > 0 && $contentLength <= 5 * 1024 * 1024) {
            Storage::put($path, $response->getBody()->getContents());
        } else {
            $resource = fopen('php://temp', 'r+');
            $stream = $response->getBody();

            while (!$stream->eof()) {
                fwrite($resource, $stream->read(1024));
            }

            rewind($resource);
            Storage::writeStream($path, $resource);
            fclose($resource);
        }

        Media::updateOrCreate(
            ['host' => $this->host, 'url' => $this->mediaUrl],
            [
                'parsed_page_id' => $parsedPage->id,
                'local_path' => $path,
                'mime_type' => $response->getHeaderLine('Content-Type') ?: 'application/octet-stream',
                'size' => Storage::size($path),
                'downloaded_at' => now(),
            ],
        );
    }

    protected function generateLocalPath(): string
    {
        $extension = pathinfo(
            parse_url($this->mediaUrl, PHP_URL_PATH) ?? '',
            PATHINFO_EXTENSION,
        );

        $hash = md5($this->mediaUrl);
        $filename = uniqid($hash . '_', true);

        if ($extension) {
            $filename .= '.' . $extension;
        }

        return "content-collector/{$this->host}/{$filename}";
    }
}
