<?php

namespace JothamLec\MarketingToolkit;

/**
 * Everything the <head> prints for one page, resolved. The view only formats it.
 */
final readonly class Meta
{
    /**
     * @param  array{url: string, width: int, height: int, alt: ?string}|null  $image
     * @param  array<string, string>  $verification  meta name => content
     * @param  list<array<string, mixed>>  $graph  JSON-LD nodes
     * @param  array<string, string>  $alternates  hreflang code => the page in that language
     * @param  list<string>  $localeAlternates  og:locale:alternate
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
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );
    }
}
