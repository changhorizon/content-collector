<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace ChangHorizon\ContentCollector\Services;

use ChangHorizon\ContentCollector\Contracts\PageParserInterface;
use ChangHorizon\ContentCollector\DTO\ParseResult;
use DOMDocument;
use DOMXPath;
use Throwable;

class SimpleHtmlParser implements PageParserInterface
{
    public function parse(string $html): ParseResult
    {
        libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument();
            $dom->loadHTML($html);

            $xpath = new DOMXPath($dom);

            // ----------------------------
            // title
            // ----------------------------
            $titleNode = $xpath->query('//title')->item(0);
            $title = $titleNode ? trim($titleNode->textContent) : null;

            // ----------------------------
            // body html
            // ----------------------------
            $bodyNode = $xpath->query('//body')->item(0);
            $bodyHtml = $bodyNode
                ? $dom->saveHTML($bodyNode)
                : null;

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
            // media urls
            // ----------------------------
            $mediaUrls = [];

            foreach ($xpath->query('//img[@src]') as $node) {
                $src = trim($node->getAttribute('src'));
                if ($src !== '') {
                    $mediaUrls[] = $src;
                }
            }

            foreach ($xpath->query('//video[@src] | //audio[@src] | //source[@src]') as $node) {
                $src = trim($node->getAttribute('src'));
                if ($src !== '') {
                    $mediaUrls[] = $src;
                }
            }

            // ----------------------------
            // meta
            // ----------------------------
            $meta = [];

            // html lang
            $htmlNode = $xpath->query('//html')->item(0);
            if ($htmlNode && $htmlNode->hasAttribute('lang')) {
                $meta['lang'] = trim($htmlNode->getAttribute('lang'));
            }

            // charset
            $charsetNode = $xpath->query('//meta[@charset]')->item(0);
            if ($charsetNode) {
                $meta['charset'] = strtolower(trim($charsetNode->getAttribute('charset')));
            }

            // meta name / property
            foreach ($xpath->query('//meta[@name] | //meta[@property]') as $node) {
                $key = $node->getAttribute('name') ?: $node->getAttribute('property');
                $value = $node->getAttribute('content');

                if ($key !== '' && $value !== '') {
                    $meta[strtolower($key)] = trim($value);
                }
            }

            // canonical
            $canonical = $xpath->query('//link[@rel="canonical"]')->item(0);
            if ($canonical && $canonical->hasAttribute('href')) {
                $meta['canonical'] = trim($canonical->getAttribute('href'));
            }

            return new ParseResult(
                success: true,
                title: $title,
                bodyHtml: $bodyHtml,
                links: array_values(array_unique($links)),
                mediaUrls: array_values(array_unique($mediaUrls)),
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
