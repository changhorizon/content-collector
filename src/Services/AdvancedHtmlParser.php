<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\Contracts\PageParserInterface;
use ChangHorizon\ContentCollector\DTO\ParseResult;
use DOMDocument;
use DOMXPath;
use Throwable;

class AdvancedHtmlParser implements PageParserInterface
{
    public function parse(string $html): ParseResult
    {
        libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument();
            $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);

            $xpath = new DOMXPath($dom);

            // ----------------------------
            // title
            // ----------------------------
            $title = null;
            if ($node = $xpath->query('//title')->item(0)) {
                $title = trim($node->textContent);
            }

            // ----------------------------
            // body html
            // ----------------------------
            $bodyHtml = null;
            if ($body = $xpath->query('//body')->item(0)) {
                $bodyHtml = $dom->saveHTML($body);
            }

            // ----------------------------
            // links
            // ----------------------------
            $links = [];
            foreach ($xpath->query('//a[@href]') as $node) {
                $href = trim($node->getAttribute('href'));
                if ($href !== '') {
                    $links[] = $href;
                }
            }

            // ----------------------------
            // media urls (核心升级)
            // ----------------------------
            $mediaUrls = [];

            // 1. img src
            foreach ($xpath->query('//img[@src]') as $node) {
                $mediaUrls[] = trim($node->getAttribute('src'));
            }

            // 2. img lazy attrs
            foreach ($xpath->query('//img[@data-src or @data-lazy or @data-original]') as $node) {
                foreach (['data-src', 'data-lazy', 'data-original'] as $attr) {
                    if ($node->hasAttribute($attr)) {
                        $mediaUrls[] = trim($node->getAttribute($attr));
                    }
                }
            }

            // 3. video / audio / source
            foreach ($xpath->query('//video[@src] | //audio[@src] | //source[@src]') as $node) {
                $mediaUrls[] = trim($node->getAttribute('src'));
            }

            // 4. iframe（视频站点关键）
            foreach ($xpath->query('//iframe[@src]') as $node) {
                $mediaUrls[] = trim($node->getAttribute('src'));
            }

            // 5. inline style background-image
            foreach ($xpath->query('//*[@style]') as $node) {
                $style = $node->getAttribute('style');
                if (preg_match_all('/background-image\s*:\s*url\((["\']?)(.*?)\1\)/i', $style, $matches)) {
                    foreach ($matches[2] as $url) {
                        $mediaUrls[] = trim($url);
                    }
                }
            }

            // 去重 & 过滤空值
            $mediaUrls = array_values(array_unique(array_filter($mediaUrls)));

            // ----------------------------
            // meta
            // ----------------------------
            $meta = [];

            foreach ($xpath->query('//meta[@name or @property]') as $node) {
                $key = $node->getAttribute('name') ?: $node->getAttribute('property');
                $value = $node->getAttribute('content');
                if ($key && $value) {
                    $meta[strtolower($key)] = trim($value);
                }
            }

            return new ParseResult(
                success: true,
                title: $title,
                bodyHtml: $bodyHtml,
                links: array_values(array_unique($links)),
                mediaUrls: $mediaUrls,
                meta: $meta,
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
}
