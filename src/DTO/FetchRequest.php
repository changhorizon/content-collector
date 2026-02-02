<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\DTO;

final class FetchRequest
{
    public function __construct(
        public readonly array $headers = [],
        public readonly ?int $timeout = null,
        public readonly ?string $proxy = null,
        public readonly bool $allowRedirects = true,
        public readonly int $maxRedirects = 5,
    ) {
    }

    public function toHttpOptions(): array
    {
        $options = [];

        if ($this->headers) {
            $options['headers'] = $this->headers;
        }

        if ($this->timeout !== null) {
            $options['timeout'] = $this->timeout;
        }

        if ($this->proxy) {
            $options['proxy'] = $this->proxy;
        }

        $options['allow_redirects'] = $this->allowRedirects
            ? ['max' => $this->maxRedirects]
            : false;

        return $options;
    }
}
