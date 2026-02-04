<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\DTO;

use ChangHorizon\ContentCollector\Enums\FetchResultContentType;
use Psr\Http\Message\StreamInterface;

readonly class FetchResult
{
    public function __construct(
        public bool $success,
        public ?int $statusCode = null,
        public ?FetchResultContentType $contentType = null,
        public array $headers = [],
        public ?string $body = null,
        public ?string $bodyHash = null,
        public ?StreamInterface $stream = null,
        public ?string $error = null,
    ) {
    }

    public function isHtml(): bool
    {
        return $this->success
            && $this->contentType === FetchResultContentType::HTML;
    }

    public function isStream(): bool
    {
        return $this->success
            && $this->contentType === FetchResultContentType::STREAM;
    }

    public function contentTypeHeader(): string
    {
        if (!$this->success) {
            return '';
        }

        $header = $this->headers['content-type'] ?? null;

        if (is_array($header)) {
            return $header[0] ?? '';
        }

        return is_string($header) ? $header : '';
    }
}
