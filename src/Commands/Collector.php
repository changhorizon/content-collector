<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Commands;

use ChangHorizon\ContentCollector\Services\ConfigParamsBuilder;
use ChangHorizon\ContentCollector\Services\Crawler;
use Illuminate\Console\Command;
use InvalidArgumentException;

class Collector extends Command
{
    protected $signature = 'content-collector:run {host}';
    protected $description = 'Run ContentCollector for a specific host';

    public function handle(): int
    {
        try {
            $host = (string) $this->argument('host');

            $config = config('content-collector');
            if (!isset($config['sites'][$host])) {
                throw new InvalidArgumentException("Host [$host] not found in content-collector.sites config");
            }

            // 构建配置参数
            $params = (new ConfigParamsBuilder($host, $config))->build();

            // 处理 params['site']['entry'] 为空的情况
            if (!isset($params['site']['entry'])) {
                throw new InvalidArgumentException("Host [$host] missing site.entry config");
            }

            // 创建 Crawler 实例并运行
            (new Crawler($host, $params))->run();
        } catch (\Exception $e) {
            $this->error('Error running content collector: ' . $e->getMessage());
        }

        return self::SUCCESS;
    }
}
