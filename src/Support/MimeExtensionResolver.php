<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Support;

use Symfony\Component\Mime\MimeTypes;

final class MimeExtensionResolver
{
    public static function resolve(?string $contentType): ?string
    {
        if ($contentType === null) {
            return null;
        }

        // 去掉 charset / boundary 等参数
        $mime = strtolower(trim(explode(';', $contentType)[0]));

        $extensions = MimeTypes::getDefault()->getExtensions($mime);

        return $extensions[0] ?? null;
    }
}
