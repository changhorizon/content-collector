<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\Support\UrlNormalizer;

final readonly class LinkExtractor
{
    public function __construct(
        private string $taskHost,
    ) {
    }

    public function extract(array $rawLinks, string $baseUrl): array
    {
        $clean = [];

        foreach ($rawLinks as $raw) {
            $url = UrlNormalizer::normalize($raw, $baseUrl);
            if (! $url) {
                continue;
            }

            $host = parse_url($url, PHP_URL_HOST);
            if ($host !== $this->taskHost) {
                continue; // 👈 铁门
            }

            $clean[] = $url;
        }

        return array_values(array_unique($clean));
    }
}
