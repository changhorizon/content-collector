<?php

/** @noinspection ALL */

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
            $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

            $xpath = new DOMXPath($dom);

            /* =============================
             * Title
             * ============================= */
            $titleNode = $xpath->query('//title')->item(0);
            $title = $titleNode ? trim($titleNode->textContent) : null;

            /* =============================
             * Body HTML
             * ============================= */
            $bodyNode = $xpath->query('//body')->item(0);
            $bodyHtml = $bodyNode ? $dom->saveHTML($bodyNode) : null;

            /* =============================
             * Links
             * ============================= */
            $links = [];
            foreach ($xpath->query('//a[@href]') as $node) {
                $href = trim($node->getAttribute('href'));
                if ($href !== '') {
                    $links[] = $href;
                }
            }

            /* =============================
             * Media URLs（🔥重点）
             * ============================= */
            $mediaUrls = [];

            // ---- img / video / audio / source ----
            foreach ($xpath->query('//img | //video | //audio | //source') as $node) {
                $this->collectUrlAttrs(
                    $node,
                    [
                        'src',
                        'data-src',
                        'data-original',
                        'data-lazy',
                        'data-url',
                    ],
                    $mediaUrls,
                );

                // srcset
                if ($node->hasAttribute('srcset')) {
                    $this->parseSrcSet(
                        $node->getAttribute('srcset'),
                        $mediaUrls,
                    );
                }
            }

            // ---- picture/source ----
            foreach ($xpath->query('//picture//source[@srcset]') as $node) {
                $this->parseSrcSet(
                    $node->getAttribute('srcset'),
                    $mediaUrls,
                );
            }

            // ---- inline style background-image ----
            foreach ($xpath->query('//*[@style]') as $node) {
                $style = $node->getAttribute('style');
                $this->parseCssUrls($style, $mediaUrls);
            }

            /* =============================
             * Meta
             * ============================= */
            $meta = [];

            $htmlNode = $xpath->query('//html')->item(0);
            if ($htmlNode && $htmlNode->hasAttribute('lang')) {
                $meta['lang'] = trim($htmlNode->getAttribute('lang'));
            }

            $charsetNode = $xpath->query('//meta[@charset]')->item(0);
            if ($charsetNode) {
                $meta['charset'] = strtolower(trim($charsetNode->getAttribute('charset')));
            }

            foreach ($xpath->query('//meta[@name] | //meta[@property]') as $node) {
                $key = $node->getAttribute('name') ?: $node->getAttribute('property');
                $value = $node->getAttribute('content');

                if ($key !== '' && $value !== '') {
                    $meta[strtolower($key)] = trim($value);
                }
            }

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

    /* =========================================================
     * Helpers
     * ========================================================= */

    protected function collectUrlAttrs($node, array $attrs, array &$bucket): void
    {
        foreach ($attrs as $attr) {
            if ($node->hasAttribute($attr)) {
                $val = trim($node->getAttribute($attr));
                if ($val !== '') {
                    $bucket[] = $val;
                }
            }
        }
    }

    protected function parseSrcSet(string $srcset, array &$bucket): void
    {
        foreach (explode(',', $srcset) as $part) {
            $url = trim(explode(' ', trim($part))[0]);
            if ($url !== '') {
                $bucket[] = $url;
            }
        }
    }

    protected function parseCssUrls(string $css, array &$bucket): void
    {
        if (preg_match_all('/url\((["\']?)(.*?)\1\)/i', $css, $matches)) {
            foreach ($matches[2] as $url) {
                $url = trim($url);
                if ($url !== '') {
                    $bucket[] = $url;
                }
            }
        }
    }
}
