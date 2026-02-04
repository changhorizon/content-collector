<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\DTO;

final readonly class StoredMedia
{
    public function __construct(
        public string $path,           // 最终 storage path（含扩展名）
        public int $bytes,
        public string $hash,
        public int $httpStatus,
        public ?string $contentType,
        public ?string $extension,
        public bool $skipped = false,
        public ?string $skipReason = null,
    ) {
    }

    public static function skipped(
        int $httpStatus,
        ?string $contentType,
        string $reason,
    ): self {
        return new self(
            path: '',
            bytes: 0,
            hash: '',
            httpStatus: $httpStatus,
            contentType: $contentType,
            extension: null,
            skipped: true,
            skipReason: $reason,
        );
    }
}
