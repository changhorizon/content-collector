<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Enums;

enum FetchResultContentType: string
{
    case HTML = 'html';
    case STREAM = 'stream';
}
