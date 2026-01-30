<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Factories;

use GuzzleHttp\Client;

class HttpClientFactory
{
    public static function create(array $config, ?Client $mockClient = null): Client
    {
        if ($mockClient) {
            return $mockClient;
        }

        $headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Connection' => 'keep-alive',
            'User-Agent' => $config['user_agent'],
        ];

        return new Client([
            'timeout' => $config['http_timeout'],
            'headers' => $headers,
        ]);
    }
}
