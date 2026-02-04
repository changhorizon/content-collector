<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use InvalidArgumentException;

/**
 * @phpstan-type RedisParams array{
 *   enabled: bool,
 *   host_key_prefix: string,
 *   task_count_prefix: string,
 *   max_concurrent_per_host: int
 * }
 *
 * @phpstan-type QueuesParams array{
 *    default: string,
 *    crawl: string,
 *    parse: string,
 *    media: string
 *  }
 *
 * @phpstan-type ConfineParams array{
 *   delay_ms: int,
 *   jitter_ms: int,
 *   max_urls: int
 * }
 *
 * @phpstan-type ClientParams array{
 *   timeout: int,
 *   headers: array{User-Agent: string}
 * }
 *
 * @phpstan-type ProxyParams array{
 *   url: string|null,
 *   scopes: list<'html'|'media'>
 * }
 *
 * @phpstan-type SiteParams array{
 *   entry: string,
 *   priority: string,
 *   allow: list<string>,
 *   deny: list<string>
 * }
 *
 * @phpstan-type CrawlParams array{
 *   redis: RedisParams,
 *   queues: QueuesParams,
 *   confine: ConfineParams,
 *   client: ClientParams,
 *   proxy: ProxyParams,
 *   site: SiteParams
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
        'queues' => [
            'default' => 'cc-default',
            'crawl' => 'cc-crawl',
            'parse' => 'cc-parse',
            'media' => 'cc-media',
        ],
        'confine' => [
            'delay_ms' => 1500,
            'jitter_ms' => 500,
            'max_urls' => 10000,
        ],
        'client' => [
            'timeout' => 15,
            'user_agents' => ['Mozilla/5.0'],
        ],
        'proxy' => [
            'enabled' => false,
            'url' => 'http://192.168.56.10:3000',
            'scopes' => ['html'],
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

        $this->validateQueues($merged['queues']);
        $this->validateConfine($merged['confine']);
        $this->validateClient($merged['client']);
        $this->validateProxy($merged['proxy']);
        $this->validateSite($merged['site']);

        $merged['client']['user_agents'] = array_values(array_filter(
            $merged['client']['user_agents'],
            static fn ($ua) => is_string($ua) && trim($ua) !== '',
        ));

        if ($merged['client']['user_agents'] === []) {
            throw new InvalidArgumentException('Client [user_agents] resolved to empty after normalization.');
        }

        $merged['client'] = $this->buildClient($merged['client']);
        $merged['proxy'] = $this->buildProxy($merged['proxy']);

        return $merged;
    }

    protected function buildClient(array $client): array
    {
        $uaList = array_values($client['user_agents']);
        $userAgent = $uaList[array_rand($uaList)];

        // user_agents 是配置期语义，在此处被“编译掉”
        return [
            'timeout' => $client['timeout'],
            'headers' => ['User-Agent' => $userAgent],
        ];
    }

    protected function buildProxy(array $proxy): array
    {
        if (!$proxy['enabled']) {
            return [
                'url' => null,
                'scopes' => [],
            ];
        }

        $scopes = array_values(array_filter(
            $proxy['scopes'] ?? ['html'],
            static fn ($s) => in_array($s, ['html', 'media'], true),
        ));

        // enabled 是配置期语义，在此处被“编译掉”
        return [
            'url' => $proxy['url'],
            'scopes' => $scopes,
        ];
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
        if (!is_int($client['timeout']) || $client['timeout'] <= 0) {
            throw new InvalidArgumentException('Client [timeout] must be positive integer.');
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

    protected function validateProxy(array $proxy): void
    {
        if (!is_bool($proxy['enabled'])) {
            throw new InvalidArgumentException('Proxy [enabled] must be boolean.');
        }

        if ($proxy['enabled']) {
            if (!is_string($proxy['url']) || trim($proxy['url']) === '') {
                throw new InvalidArgumentException('Proxy [url] must be non-empty string.');
            }

            if (!filter_var($proxy['url'], FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Proxy [url] must be valid URL.');
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
