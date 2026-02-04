<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\Support\UrlNormalizer;
use Illuminate\Support\Facades\Log;

final class MediaNormalizer
{
    public function normalize(array $rawUrls, string $baseUrl): array
    {
        $clean = [];

        foreach ($rawUrls as $raw) {
            $url = UrlNormalizer::normalize($raw, $baseUrl);
            if (! $url) {
                Log::debug('Media dropped by normalizer', [
                    'raw' => $raw,
                ]);
                continue;
            }

            // ❌ 不做 host 判断
            $clean[] = $url;
        }

        return array_values(array_unique($clean));
    }
}
