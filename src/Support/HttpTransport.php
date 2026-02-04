<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Support;

use ChangHorizon\ContentCollector\Contracts\HttpTransportInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

/**
 * Default HTTP transport based on Laravel Http facade.
 *
 * - Returns raw PSR-7 responses
 * - No business logic
 * - No error handling
 */
class HttpTransport implements HttpTransportInterface
{
    /**
     * @throws ConnectionException
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return Http::withOptions($options)
            ->send($method, $url)
            ->toPsrResponse();
    }

    /**
     * @throws ConnectionException
     */
    public function stream(string $method, string $url, array $options = []): ResponseInterface
    {
        // 明确声明：这是 stream 请求
        $options['stream'] = true;

        return Http::withOptions($options)
            ->send($method, $url)
            ->toPsrResponse();
    }
}
