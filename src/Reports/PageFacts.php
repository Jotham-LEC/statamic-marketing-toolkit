<?php

namespace JothamLec\MarketingToolkit\Reports;

/**
 * What one rendered page says about itself, as the checks need it.
 */
final readonly class PageFacts
{
    /**
     * @param  list<string>  $brokenLinks  paths on this site that lead nowhere
     * @param  list<string>  $redirectedLinks  paths answered by a redirect rule
     * @param  list<string>  $externalLinks  its links to other sites
     * @param  list<string>  $brokenExternalLinks  those that lead nowhere (when checked)
     * @param  bool  $inSitemap  whether the sitemap lists the page (not read from the HTML)
     */
    public function __construct(
        public int $status = 200,
        public ?string $error = null,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $robots = null,
        public array $brokenLinks = [],
        public array $redirectedLinks = [],
        public array $externalLinks = [],
        public array $brokenExternalLinks = [],
        public ?string $ogImage = null,
        public bool $inSitemap = false,
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
