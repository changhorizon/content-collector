<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Support;

final class PathMatcher
{
    /**
     * 是否匹配任一 pattern
     */
    public static function matches(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path, FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }
}
