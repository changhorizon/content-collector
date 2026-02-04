<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Policies;

use ChangHorizon\ContentCollector\Support\PathMatcher;
use Illuminate\Support\Facades\Log;

final class ContentPersistencePolicy
{
    /**
     * ⚠️ 本 Policy 仅决定「是否持久化内容」
     * ⚠️ 不得用于控制 URL 是否 discover / schedule
     */
    public function decide(
        array $params,
        string $url,
    ): PersistenceDecision {
        /**
         * ① 读取并清洗规则（防 explode('') 陷阱）
         */
        $site = $params['site'] ?? [];

        $priority = $site['rule_priority'] ?? 'black';
        $allow = $this->sanitizeRules($site['allow'] ?? []);
        $deny = $this->sanitizeRules($site['deny'] ?? []);

        if (!in_array($priority, ['black', 'white'], true)) {
            $priority = 'black';
        }

        /**
         * ② 计算 path
         */
        $path = parse_url($url, PHP_URL_PATH);
        $path = $path ? strtolower('/' . ltrim(rawurldecode($path), '/')) : '/';

        Log::info("Evaluating path: $path");
        Log::info('Allow rules: ' . implode(', ', $allow));
        Log::info('Deny rules: ' . implode(', ', $deny));
        /**
         * ③ 策略裁决（只影响 persist）
         */
        if ($priority === 'black') {
            if ($deny && PathMatcher::matches($path, $deny)) {
                return PersistenceDecision::deny('path_denied');
            }

            if ($allow && !PathMatcher::matches($path, $allow)) {
                return PersistenceDecision::skip('path_not_allowed');
            }
        } else {
            if ($allow && !PathMatcher::matches($path, $allow)) {
                return PersistenceDecision::skip('path_not_allowed');
            }

            if ($deny && PathMatcher::matches($path, $deny)) {
                return PersistenceDecision::deny('path_denied');
            }
        }

        return PersistenceDecision::allow();
    }

    /**
     * 规则清洗：
     * - 移除空字符串
     * - 保证 [] 表示「未配置规则」
     */
    private function sanitizeRules(array $rules): array
    {
        return array_values(array_filter(
            $rules,
            static fn ($v) => is_string($v) && $v !== '',
        ));
    }
}
