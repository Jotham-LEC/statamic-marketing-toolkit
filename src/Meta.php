<?php

namespace JothamLec\MarketingToolkit;

/**
 * This class holds everything the <head> prints for one page, already resolved. The view only formats it.
 */
final readonly class Meta
{
    /**
     * @param  array{url: string, width: int, height: int, alt: ?string}|null  $image
     * @param  array<string, string>  $verification  meta name => content
     * @param  list<array<string, mixed>>  $graph  JSON-LD nodes
     * @param  array<string, string>  $alternates  hreflang code => the page's address in that language
     * @param  list<string>  $localeAlternates  the og:locale:alternate values
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $canonical,
        public string $robots,
        public string $ogTitle,
        public string $ogType,
        public string $url,
        public string $siteName,
        public string $locale,
        public ?array $image,
        public ?string $published,
        public ?string $modified,
        public ?string $twitterSite,
        public array $verification,
        public array $graph,
        public array $alternates = [],
        public array $localeAlternates = [],
    ) {}

    public function jsonLd(): ?string
    {
        if ($this->graph === []) {
            return null;
        }

        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $this->graph],
            // Text that isn't UTF-8, such as text pasted from another program, becomes U+FFFD rather than failing.
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );
    }
}
