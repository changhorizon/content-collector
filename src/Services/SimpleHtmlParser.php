<?php

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\Contracts\PageParserInterface;
use ChangHorizon\ContentCollector\DTO\ParseResult;
use DOMDocument;
use DOMXPath;
use Throwable;

class SimpleHtmlParser implements PageParserInterface
{
    public function parse(string $html, string $baseUrl): ParseResult
    {
        libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument();
            $dom->loadHTML($html);

            $xpath = new DOMXPath($dom);

            $titleNode = $xpath->query('//title')->item(0);
            $title = $titleNode ? trim($titleNode->textContent) : null;

            $bodyNode = $xpath->query('//body')->item(0);
            $bodyHtml = $bodyNode
                ? $dom->saveHTML($bodyNode)
                : null;

            $links = [];
            foreach ($xpath->query('//a[@href]') as $node) {
                $href = trim($node->getAttribute('href'));
                if ($href !== '') {
                    $links[] = $this->resolveUrl($href, $baseUrl);
                }
            }

            return new ParseResult(
                success: true,
                title: $title,
                bodyHtml: $bodyHtml,
                links: array_values(array_unique($links)),
            );
        } catch (Throwable $e) {
            return new ParseResult(
                success: false,
                error: $e->getMessage(),
            );
        } finally {
            libxml_clear_errors();
        }
    }

    protected function resolveUrl(string $href, string $base): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        return rtrim($base, '/') . '/' . ltrim($href, '/');
    }
}
