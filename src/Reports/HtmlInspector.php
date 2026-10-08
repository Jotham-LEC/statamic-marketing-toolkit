<?php

namespace JothamLec\MarketingToolkit\Reports;

use DOMDocument;
use DOMElement;
use DOMXPath;
use JothamLec\MarketingToolkit\Support\Uris;
use Statamic\Facades\Site;

/**
 * Reads a rendered page for the checks: title, description, h1s, canonical,
 * robots, images, links back into this site, the share image and JSON-LD.
 */
class HtmlInspector
{
    public function __construct(private LinkChecker $links) {}

    public function inspect(string $html): PageFacts
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

        $links = $this->links($xpath);
        [$jsonLd, $jsonLdErrors] = $this->jsonLd($xpath);

        return new PageFacts(
            status: 200,
            title: $this->text($xpath, '//head/title'),
            description: $this->attribute($xpath, '//head/meta[@name="description"]', 'content'),
            h1s: array_values(array_map(fn ($h1) => trim(preg_replace('/\s+/', ' ', $h1->textContent)), iterator_to_array($xpath->query('//body//h1')))),
            canonical: $this->attribute($xpath, '//head/link[@rel="canonical"]', 'href'),
            robots: $this->attribute($xpath, '//head/meta[@name="robots"]', 'content'),
            images: $images->length,
            imagesWithoutAlt: $imagesWithoutAlt,
            brokenLinks: $links->broken,
            redirectedLinks: $links->redirected,
            internalLinks: $links->internal,
            externalLinks: $links->external,
            ogImage: $this->attribute($xpath, '//head/meta[@property="og:image"]', 'content'),
            jsonLd: $jsonLd,
            jsonLdErrors: $jsonLdErrors,
        );
    }

    /**
     * The page's links: broken and redirected paths on this site, every path
     * on this site it links to, and its links to other sites.
     */
    private function links(DOMXPath $xpath): PageLinks
    {
        // The report's site: the Runner makes it the current one.
        $host = parse_url(Site::current()->absoluteUrl(), PHP_URL_HOST);
        $broken = $redirected = $internal = $external = [];

        foreach ($xpath->query('//body//a[@href]') as $link) {
            /** @var DOMElement $link */
            $href = trim($link->getAttribute('href'));

            if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript|data|sms):#i', $href)) {
                continue;
            }

            $parts = parse_url($href);

            if ($parts === false || (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true))) {
                continue;
            }

            if (isset($parts['host']) && strcasecmp($parts['host'], (string) $host) !== 0) {
                $external[] = strtok($href, '#');

                continue;
            }

            $path = $parts['path'] ?? '/';

            // A relative link ("next-page") is relative to nothing the checker knows; skip it.
            if (! str_starts_with($path, '/')) {
                continue;
            }

            $internal[] = Uris::normalizePath($path);

            match ($this->links->check($path)) {
                'broken' => $broken[] = $path,
                'redirect' => $redirected[] = $path,
                default => null,
            };
        }

        $once = fn (array $links) => array_values(array_unique($links));

        return new PageLinks($once($broken), $once($redirected), $once($internal), $once($external));
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
