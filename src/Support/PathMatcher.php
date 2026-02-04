<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Support;

use Illuminate\Support\Facades\Log;

final class PathMatcher
{
    /**
     * 是否匹配任一 pattern
     */
    public static function matches(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            Log::info("Matching path '$path' against pattern '$pattern'");
            //            if (fnmatch($pattern, $path, FNM_CASEFOLD)) {
            //                return true;
            //            }
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
