<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Enums;

enum ReferenceRelation: string
{
    case LINK = 'link';
    case EMBED = 'embed';
    case IMPORT = 'import';
    case PRELOAD = 'preload';
    case REDIRECT = 'redirect';
    case CANONICAL = 'canonical';
}
