<?php

namespace JothamLec\MarketingToolkit\Reports;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Statamic\Facades\Site;

/**
 * Reads a rendered page for the checks: title, description, robots, its
 * links, and the share image.
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

        [$broken, $redirected, $external] = $this->links($xpath);

        return new PageFacts(
            status: $status,
            title: $this->text($xpath, '//head/title'),
            description: $this->attribute($xpath, '//head/meta[@name="description"]', 'content'),
            robots: $this->attribute($xpath, '//head/meta[@name="robots"]', 'content'),
            brokenLinks: $broken,
            redirectedLinks: $redirected,
            externalLinks: $external,
            ogImage: $this->attribute($xpath, '//head/meta[@property="og:image"]', 'content'),
        );
    }

    /**
     * The page's links: broken and redirected paths on this site, and its
     * links to other sites.
     *
     * @return array{0: list<string>, 1: list<string>, 2: list<string>}
     */
    private function links(DOMXPath $xpath): array
    {
        // The report's site: the Runner makes it the current one.
        $host = parse_url(Site::current()->absoluteUrl(), PHP_URL_HOST);
        $broken = $redirected = $external = [];

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

            match ($this->links->check($path)) {
                'broken' => $broken[] = $path,
                'redirect' => $redirected[] = $path,
                default => null,
            };
        }

        return array_map(fn (array $links) => array_values(array_unique($links)), [$broken, $redirected, $external]);
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
