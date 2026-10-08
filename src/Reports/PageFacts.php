<?php

namespace JothamLec\MarketingToolkit\Reports;

/**
 * Holds what one rendered page says about itself, in the form the checks need.
 */
final readonly class PageFacts
{
    /**
     * @param  list<string>  $h1s
     * @param  list<string>  $brokenLinks  paths on this site that lead nowhere
     * @param  list<string>  $redirectedLinks  paths answered by a redirect rule
     * @param  list<string>  $internalLinks  every path on this site the page links to
     * @param  list<string>  $externalLinks  the page's links to other sites
     * @param  list<string>  $brokenExternalLinks  the links to other sites that lead nowhere, when they were checked
     * @param  list<string>  $jsonLdErrors
     * @param  bool  $inSitemap  whether the sitemap lists the page, which is not read from the HTML
     * @param  ?string  $exception  the class of the exception thrown by a page that didn't render
     */
    public function __construct(
        public int $status = 200,
        public ?string $error = null,
        public ?string $title = null,
        public ?string $description = null,
        public array $h1s = [],
        public ?string $canonical = null,
        public ?string $robots = null,
        public int $images = 0,
        public int $imagesWithoutAlt = 0,
        public array $brokenLinks = [],
        public array $redirectedLinks = [],
        public array $internalLinks = [],
        public array $externalLinks = [],
        public array $brokenExternalLinks = [],
        public ?string $ogImage = null,
        public int $jsonLd = 0,
        public array $jsonLdErrors = [],
        public bool $inSitemap = false,
        public ?string $exception = null,
    ) {}

    public function rendered(): bool
    {
        return $this->status === 200 && $this->error === null;
    }

    public function noindex(): bool
    {
        return str_contains(strtolower((string) $this->robots), 'noindex');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    public static function fromArray(array $facts): self
    {
        return new self(...array_intersect_key($facts, get_class_vars(self::class)));
    }
}
