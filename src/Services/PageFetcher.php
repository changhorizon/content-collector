<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\Contracts\HttpTransportInterface;
use ChangHorizon\ContentCollector\Contracts\PageFetcherInterface;
use ChangHorizon\ContentCollector\DTO\FetchRequest;
use ChangHorizon\ContentCollector\DTO\FetchResult;
use ChangHorizon\ContentCollector\Enums\FetchResultContentType;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class PageFetcher implements PageFetcherInterface
{
    public function __construct(
        protected HttpTransportInterface $transport,
    ) {
    }

    public function fetch(string $url, FetchRequest $request): FetchResult
    {
        try {
            $finalUrl = $this->buildRequestUrl($url, $request);

            $response = $this->transport->request(
                'GET',
                $finalUrl,
                $request->toHttpOptions(),
            );

            return $this->buildFetchResult($response);
        } catch (\Throwable $e) {
            return new FetchResult(
                success: false,
                error: $e->getMessage(),
            );
        }
    }

    /**
     * Convert PSR-7 response into FetchResult.
     */
    protected function buildFetchResult(ResponseInterface $response): FetchResult
    {
        $status = $response->getStatusCode();
        $headers = array_change_key_case($response->getHeaders(), CASE_LOWER);

        $contentType = $this->determineContentType($headers);

        // 非 2xx：强制读取为 string（错误页可能可解析）
        if ($status < 200 || $status >= 300) {
            $body = (string) $response->getBody();

            return new FetchResult(
                success: false,
                statusCode: $status,
                contentType: $contentType,
                headers: $headers,
                body: $body,
                bodyHash: $body !== '' ? hash('sha256', $body) : null,
                stream: null,
                error: 'HTTP request failed',
            );
        }

        // 2xx：根据 ContentType 决定 body 形态
        if ($contentType === FetchResultContentType::HTML) {
            $body = (string) $response->getBody();

            return new FetchResult(
                success: true,
                statusCode: $status,
                contentType: $contentType,
                headers: $headers,
                body: $body,
                bodyHash: $body !== '' ? hash('sha256', $body) : null,
                stream: null,
            );
        }

        // STREAM：保留 PSR-7 StreamInterface
        $stream = $response->getBody();

        Log::info('Fetch headers', $headers);

        return new FetchResult(
            success: true,
            statusCode: $status,
            contentType: $contentType,
            headers: $headers,
            body: null,
            bodyHash: null,     // 👈 不能也不应该现在算
            stream: $stream,
        );
    }

    private function determineContentType(array $headers): FetchResultContentType
    {
        $contentType = $headers['content-type'][0] ?? '';

        if (str_contains($contentType, 'text/html')) {
            return FetchResultContentType::HTML;
        }

        return FetchResultContentType::STREAM;
    }

    /**
     * 构建最终请求 URL
     *
     * 当 request.proxy 不为 null 时，表示通过 Headless / Playwright
     * Proxy 服务间接获取 HTML 内容
     */
    protected function buildRequestUrl(string $url, FetchRequest $request): string
    {
        if ($request->proxy === null) {
            return $url;
        }

        return rtrim($request->proxy, '/')
            . '?url='
            . urlencode($url);
    }
}
