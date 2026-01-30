<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\Contracts\PageFetcherInterface;
use ChangHorizon\ContentCollector\DTO\FetchResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpPageFetcher implements PageFetcherInterface
{
    public function fetch(string $url, array $options = []): FetchResult
    {
        try {
            $response = Http::withOptions($options)->get($url);

            if (!$response->successful()) {
                return new FetchResult(
                    success: false,
                    statusCode: $response->status(),
                    error: 'HTTP request failed',
                );
            }

            return new FetchResult(
                success: true,
                statusCode: $response->status(),
                body: $response->body(),
                headers: $response->getHeaders(),
            );
        } catch (Throwable $e) {
            return new FetchResult(
                success: false,
                error: $e->getMessage(),
            );
        }
    }
}
