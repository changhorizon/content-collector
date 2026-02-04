<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Tests\Feature;

use ChangHorizon\ContentCollector\Policies\ContentPersistencePolicy;
use ChangHorizon\ContentCollector\Tests\TestCase;

class ContentPersistencePolicyTest extends TestCase
{
    public function test_black_priority_allows_when_path_matches_allow(): void
    {
        $policy = new ContentPersistencePolicy();

        $decision = $policy->decide(
            [
                'site' => [
                    'priority' => 'black',
                    'allow' => ['#^/page$#'],
                    'deny' => [],
                ],
            ],
            'https://example.com/page',
        );

        $this->assertTrue($decision->shouldPersist);
    }

    public function test_black_priority_skips_when_path_not_in_allow(): void
    {
        $policy = new ContentPersistencePolicy();

        $decision = $policy->decide(
            [
                'site' => [
                    'priority' => 'black',
                    'allow' => ['#^/allowed$#'],
                    'deny' => [],
                ],
            ],
            'https://example.com/page',
        );

        $this->assertFalse($decision->shouldPersist);
        $this->assertSame('path_not_allowed', $decision->reason);
    }

    public function test_black_priority_denies_when_path_matches_deny(): void
    {
        $policy = new ContentPersistencePolicy();

        $decision = $policy->decide(
            [
                'site' => [
                    'priority' => 'black',
                    'allow' => ['#^/.*#'],
                    'deny' => ['#^/page$#'],
                ],
            ],
            'https://example.com/page',
        );

        $this->assertFalse($decision->shouldPersist);
        $this->assertSame('path_denied', $decision->reason);
    }

    public function test_white_priority_allows_only_when_path_matches_allow(): void
    {
        $policy = new ContentPersistencePolicy();

        $decision = $policy->decide(
            [
                'site' => [
                    'priority' => 'white',
                    'allow' => ['#^/page$#'],
                    'deny' => [],
                ],
            ],
            'https://example.com/page',
        );

        $this->assertTrue($decision->shouldPersist);
    }

    public function test_white_priority_denies_when_path_matches_deny_even_if_allowed(): void
    {
        $policy = new ContentPersistencePolicy();

        $decision = $policy->decide(
            [
                'site' => [
                    'priority' => 'white',
                    'allow' => ['#^/page$#'],
                    'deny' => ['#^/page$#'],
                ],
            ],
            'https://example.com/page',
        );

        $this->assertFalse($decision->shouldPersist);
        $this->assertSame('path_denied', $decision->reason);
    }
}
