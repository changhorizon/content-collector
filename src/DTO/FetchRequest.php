<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\DTO;

final readonly class FetchRequest
{
    public function __construct(
        public array $headers = [],
        public ?int $timeout = null,
        public ?string $proxy = null,
        public bool $allowRedirects = true,
        public int $maxRedirects = 5,
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
