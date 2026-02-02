<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\Contracts\PageFetcherInterface;
use ChangHorizon\ContentCollector\DTO\FetchRequest;
use ChangHorizon\ContentCollector\DTO\FetchResult;
use Illuminate\Support\Facades\Http;

class HttpPageFetcher implements PageFetcherInterface
{
    public function fetch(string $url, FetchRequest $request): FetchResult
    {
        try {
            $response = Http::withOptions(
                $request->toHttpOptions(),
            )->get($url);

            if (! $response->successful()) {
                return new FetchResult(
                    success: false,
                    statusCode: $response->status(),
                    error: 'HTTP request failed',
                );
            }

            return new FetchResult(
                success: true,
                statusCode: $response->status(),
                headers: array_change_key_case($response->headers(), CASE_LOWER),
                body: $response->body(),
                bodyHash: hash('sha256', $response->body()),
            );
        } catch (\Throwable $e) {
            return new FetchResult(
                success: false,
                error: $e->getMessage(),
            );
        }
    }
}
