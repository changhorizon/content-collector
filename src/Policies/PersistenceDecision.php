<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Policies;

use ChangHorizon\ContentCollector\Enums\UrlLedgerResult;

final readonly class PersistenceDecision
{
    public function __construct(
        public bool $shouldPersist,
        public UrlLedgerResult $finalResult,
        public string $reason,
    ) {
    }

    public static function allow(): self
    {
        return new self(
            shouldPersist: true,
            finalResult: UrlLedgerResult::SUCCESS,
            reason: 'persist_allowed',
        );
    }

    public static function skip(string $reason): self
    {
        return new self(
            shouldPersist: false,
            finalResult: UrlLedgerResult::SKIPPED,
            reason: $reason,
        );
    }

    public static function deny(string $reason): self
    {
        return new self(
            shouldPersist: false,
            finalResult: UrlLedgerResult::DENIED,
            reason: $reason,
        );
    }
}
