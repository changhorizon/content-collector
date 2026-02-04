<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Contracts;

use Psr\Http\Message\ResponseInterface;

interface HttpTransportInterface
{
    /**
     * 普通 HTTP 请求（非 stream）
     */
    public function request(
        string $method,
        string $url,
        array $options = [],
    ): ResponseInterface;

    /**
     * 流式请求（Downloader 专用）
     */
    public function stream(
        string $method,
        string $url,
        array $options = [],
    ): ResponseInterface;
}
