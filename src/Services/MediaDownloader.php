<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\Contracts\HttpTransportInterface;
use ChangHorizon\ContentCollector\Contracts\MediaDownloaderInterface;
use ChangHorizon\ContentCollector\DTO\FetchRequest;
use ChangHorizon\ContentCollector\DTO\StoredMedia;
use ChangHorizon\ContentCollector\Support\MimeExtensionResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class MediaDownloader implements MediaDownloaderInterface
{
    public function __construct(
        protected HttpTransportInterface $transport,
        protected Filesystem $storage,
    ) {
    }

    public function download(
        string $url,
        string $basePath,
        FetchRequest $request,
    ): StoredMedia {
        $finalUrl = $this->buildRequestUrl($url, $request);

        $options = $request->toHttpOptions();
        $options['stream'] = true;

        $response = $this->transport->stream('GET', $finalUrl, $options);

        // 1️⃣ 校验 HTTP
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("HTTP error {$status}");
        }

        // 2️⃣ 解析 Content-Type（只信 header）
        $contentType = $this->normalizeContentType(
            $response->getHeaderLine('Content-Type'),
        );

        if (
            str_contains($contentType, 'text/html') ||
            str_contains($contentType, 'application/xhtml')
        ) {
            // ❌ 不是媒体，直接丢弃
            Log::info('Skip non-media content', [
                'url' => $url,
                'content_type' => $contentType,
            ]);

            return StoredMedia::skipped(
                httpStatus: $status,
                contentType: $contentType,
                reason: 'non_media_content',
            );
        }

        $ext = MimeExtensionResolver::resolve($contentType);

        $finalPath = $ext ? "{$basePath}.{$ext}" : $basePath;

        // 3️⃣ 临时路径（强制唯一，防覆盖）
        $tmpPath = $finalPath . '.tmp.' . bin2hex(random_bytes(6));

        try {
            $result = $this->writeResponseToStorage($response, $tmpPath);
        } catch (Throwable $e) {
            $this->storage->delete($tmpPath);
            throw $e;
        }

        // 4️⃣ 原子 move（覆盖即表示“最终版本”）
        $this->storage->move($tmpPath, $finalPath);

        return new StoredMedia(
            path: $finalPath,
            bytes: $result['bytes'],
            hash: $result['hash'],
            httpStatus: $status,
            contentType: $contentType,
            extension: $ext,
        );
    }

    /**
     * ===============================
     * 核心：真正的流式写入
     * ===============================
     */
    protected function writeResponseToStorage(
        ResponseInterface $response,
        string $path,
    ): array {
        $body = $response->getBody();

        $hashCtx = hash_init('sha256');
        $bytes = 0;

        // ⚠️ 关键：直接给 storage 写流，杜绝 php://temp 覆盖问题
        $writeStream = $this->storage->writeStream(
            $path,
            $this->createPsrStreamReader($body, $hashCtx, $bytes),
        );

        if ($writeStream === false) {
            throw new RuntimeException('Storage writeStream failed');
        }

        return [
            'bytes' => $bytes,
            'hash'  => hash_final($hashCtx),
        ];
    }

    /**
     * ===============================
     * PSR-7 → PHP stream adapter
     * ===============================
     */
    protected function createPsrStreamReader(
        $psrStream,
        &$hashCtx,
        &$bytes,
    ) {
        $pipe = fopen('php://temp', 'w+');

        while (! $psrStream->eof()) {
            $chunk = $psrStream->read(8192);
            if ($chunk === '') {
                continue;
            }

            $len = strlen($chunk);
            $bytes += $len;

            hash_update($hashCtx, $chunk);
            fwrite($pipe, $chunk);
        }

        rewind($pipe);

        return $pipe;
    }

    protected function normalizeContentType(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        return strtolower(trim(explode(';', $raw)[0]));
    }

    protected function buildRequestUrl(string $url, FetchRequest $request): string
    {
        if ($request->proxy === null) {
            return $url;
        }

        return rtrim($request->proxy, '/') . '?url=' . urlencode($url);
    }
}
