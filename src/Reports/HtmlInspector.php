<?php

namespace JothamLec\Seo\Reports;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Statamic\Facades\Site;

/**
 * Reads a rendered page for the checks: title, description, h1s, canonical,
 * robots, images, links back into this site, the share image and JSON-LD.
 */
class HtmlInspector
{
    public function __construct(private LinkChecker $links) {}

    public function inspect(string $html, int $status = 200): PageFacts
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        $images = $xpath->query('//body//img');
        $imagesWithoutAlt = 0;

        foreach ($images as $image) {
            /** @var DOMElement $image */
            if (! $image->hasAttribute('alt')) {
                $imagesWithoutAlt++;
            }
        }

        [$broken, $redirected] = $this->links($xpath);
        [$jsonLd, $jsonLdErrors] = $this->jsonLd($xpath);

        return new PageFacts(
            status: $status,
            title: $this->text($xpath, '//head/title'),
            description: $this->attribute($xpath, '//head/meta[@name="description"]', 'content'),
            h1s: array_values(array_map(fn ($h1) => trim(preg_replace('/\s+/', ' ', $h1->textContent)), iterator_to_array($xpath->query('//body//h1')))),
            canonical: $this->attribute($xpath, '//head/link[@rel="canonical"]', 'href'),
            robots: $this->attribute($xpath, '//head/meta[@name="robots"]', 'content'),
            images: $images->length,
            imagesWithoutAlt: $imagesWithoutAlt,
            brokenLinks: $broken,
            redirectedLinks: $redirected,
            ogImage: $this->attribute($xpath, '//head/meta[@property="og:image"]', 'content'),
            jsonLd: $jsonLd,
            jsonLdErrors: $jsonLdErrors,
        );
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function links(DOMXPath $xpath): array
    {
        $host = parse_url(Site::current()->absoluteUrl(), PHP_URL_HOST);
        $broken = $redirected = [];

        foreach ($xpath->query('//body//a[@href]') as $link) {
            /** @var DOMElement $link */
            $href = trim($link->getAttribute('href'));

            if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript|data|sms):#i', $href)) {
                continue;
            }

            $parts = parse_url($href);

            if ($parts === false || (isset($parts['host']) && strcasecmp($parts['host'], (string) $host) !== 0) || (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true))) {
                continue;
            }

            $path = $parts['path'] ?? '/';

            // A relative link ("next-page") is relative to nothing the checker knows; skip it.
            if (! str_starts_with($path, '/')) {
                continue;
            }

            match ($this->links->check($path)) {
                'broken' => $broken[] = $path,
                'redirect' => $redirected[] = $path,
                default => null,
            };
        }

        return [array_values(array_unique($broken)), array_values(array_unique($redirected))];
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    private function jsonLd(DOMXPath $xpath): array
    {
        $count = 0;
        $errors = [];

        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $count++;
            json_decode($script->textContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Block '.$count.': '.json_last_error_msg();
            }
        }

        return [$count, $errors];
    }

    private function text(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)->item(0);
        $text = $node ? trim(preg_replace('/\s+/', ' ', $node->textContent)) : '';

        return $text === '' ? null : html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function attribute(DOMXPath $xpath, string $query, string $attribute): ?string
    {
        $node = $xpath->query($query)->item(0);
        $value = $node instanceof DOMElement ? trim($node->getAttribute($attribute)) : '';

        return $value === '' ? null : $value;
    }
}
