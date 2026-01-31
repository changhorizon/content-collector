<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Enums;

enum UrlLedgerResult: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
    case DENIED = 'denied';
}
