<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Commands;

use ChangHorizon\ContentCollector\Services\ConfigParamsBuilder;
use ChangHorizon\ContentCollector\Services\Crawler;
use Illuminate\Console\Command;
use InvalidArgumentException;

class RunCollector extends Command
{
    protected $signature = 'content-collector:run {host}';
    protected $description = 'Run ContentCollector for a specific host';

    public function handle(): void
    {
        $host = (string) $this->argument('host');

        $config = config('content-collector');
        if (!isset($config['sites'][$host])) {
            throw new InvalidArgumentException("Host [$host] not found in content-collector.sites config");
        }

        // 构建配置参数
        $params = (new ConfigParamsBuilder($host, $config))->build();
        if (!isset($params['site']['entry'])) {
            throw new InvalidArgumentException("Host [$host] missing site.entry config");
        }

        // 创建 Crawler 实例并运行
        (new Crawler($host, $params))->run();

        $this->info("ContentCollector started for host: {$host}");
    }
}
