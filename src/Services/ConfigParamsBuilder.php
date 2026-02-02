<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use InvalidArgumentException;

/**
 * @phpstan-type RedisConfig array{
 *   enabled: bool,
 *   host_key_prefix: string,
 *   task_count_prefix: string,
 *   max_concurrent_per_host: int
 * }
 *
 * @phpstan-type ConfineConfig array{
 *   delay_ms: int,
 *   jitter_ms: int,
 *   max_urls: int
 * }
 *
 * @phpstan-type ClientConfig array{
 *   http_timeout: int,
 *   user_agents: list<string>,
 *   user_agent: string
 * }
 *
 * @phpstan-type QueuesConfig array{
 *  default: string,
 *   crawl: string,
 *   parse: string,
 *   media: string
 * }
 *
 * @phpstan-type SiteConfig array{
 *   entry: string,
 *   priority: string,
 *   allow: list<string>,
 *   deny: list<string>
 * }
 *
 * @phpstan-type CrawlParams array{
 *   redis: RedisConfig,
 *   confine: ConfineConfig,
 *   client: ClientConfig,
 *   queues: QueuesConfig,
 *   site: SiteConfig,
 * }
 */
class ConfigParamsBuilder
{
    protected string $host;
    protected array $config;

    protected array $defaults = [
        'redis' => [
            'enabled' => true,
            'host_key_prefix' => 'crawler:host:',
            'task_count_prefix' => 'crawler:task:',
            'max_concurrent_per_host' => 3,
        ],
        'confine' => [
            'delay_ms' => 1500,
            'jitter_ms' => 500,
            'max_urls' => 10000,
        ],
        'client' => [
            'http_timeout' => 15,
            'user_agents' => ['Mozilla/5.0'],
        ],
        'queues' => [
            'default' => 'cc-default',
            'crawl' => 'cc-crawl',
            'parse' => 'cc-parse',
            'media' => 'cc-media',
        ],
        'site' => [
            'entry' => '',
            'priority' => 'black',
            'allow' => [],
            'deny' => [],
        ],
    ];

    public function __construct(string $host, array $config)
    {
        $this->host = $host;
        $this->config = $config;
    }

    /**
     * @return CrawlParams
     */
    public function build(): array
    {
        if (!isset($this->config['sites']) || !is_array($this->config['sites'])) {
            throw new InvalidArgumentException('Config [sites] must exist and be array.');
        }

        if (!isset($this->config['sites'][$this->host]) || !is_array($this->config['sites'][$this->host])) {
            throw new InvalidArgumentException("Config sites[{$this->host}] must be array.");
        }

        $merged = [];

        foreach ($this->defaults as $key => $default) {
            $user = $key === 'site'
                ? $this->config['sites'][$this->host]
                : ($this->config[$key] ?? []);

            if (!is_array($user)) {
                throw new InvalidArgumentException("Config [$key] must be array.");
            }

            $merged[$key] = array_merge($default, $user);
        }

        $this->validateClient($merged['client']);
        $this->validateConfine($merged['confine']);
        $this->validateQueues($merged['queues']); // ✅ 新增
        $this->validateSite($merged['site']);

        $merged['client']['user_agents'] = array_values(array_filter(
            $merged['client']['user_agents'],
            static fn ($ua) => is_string($ua) && trim($ua) !== '',
        ));

        if ($merged['client']['user_agents'] === []) {
            throw new InvalidArgumentException('Client [user_agents] resolved to empty after normalization.');
        }

        // 生成 user_agent
        $uaList = array_values($merged['client']['user_agents']);
        $merged['client']['user_agent'] = $uaList[array_rand($uaList)];

        return $merged;
    }

    protected function validateConfine(array $confine): void
    {
        foreach (['delay_ms', 'jitter_ms', 'max_urls'] as $k) {
            if (!is_int($confine[$k]) || $confine[$k] < 0) {
                throw new InvalidArgumentException("Confine [$k] must be non-negative integer.");
            }
        }
    }

    protected function validateClient(array $client): void
    {
        if (!is_int($client['http_timeout']) || $client['http_timeout'] <= 0) {
            throw new InvalidArgumentException('Client [http_timeout] must be positive integer.');
        }

        if (!is_array($client['user_agents']) || count($client['user_agents']) === 0) {
            throw new InvalidArgumentException('Client [user_agents] must be non-empty array.');
        }

        foreach ($client['user_agents'] as $ua) {
            if (!is_string($ua) || $ua === '') {
                throw new InvalidArgumentException('Each user_agent must be non-empty string.');
            }
        }
    }

    protected function validateQueues(array $queues): void
    {
        foreach (['default', 'crawl', 'parse', 'media'] as $key) {
            if (
                !isset($queues[$key]) ||
                !is_string($queues[$key]) ||
                trim($queues[$key]) === ''
            ) {
                throw new InvalidArgumentException("Queues [$key] must be non-empty string.");
            }
        }
    }

    protected function validateSite(array $site): void
    {
        if (!is_string($site['entry']) || !filter_var($site['entry'], FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Site entry for [{$this->host}] invalid.");
        }

        if (!is_array($site['allow']) || !is_array($site['deny'])) {
            throw new InvalidArgumentException('Site allow/deny must be arrays.');
        }
    }
}
