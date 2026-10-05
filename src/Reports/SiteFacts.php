<?php

namespace JothamLec\Seo\Reports;

/**
 * What the checks know about the whole run: its settings, and which titles
 * and descriptions more than one page uses.
 */
final class SiteFacts
{
    /** @var array<string, list<string>> lowercased title => urls */
    private array $titles = [];

    /** @var array<string, list<string>> lowercased description => urls */
    private array $descriptions = [];

    public function __construct(public readonly ReportSettings $settings) {}

    public function add(string $url, PageFacts $facts): void
    {
        if ($facts->title !== null) {
            $this->titles[mb_strtolower($facts->title)][] = $url;
        }

        if ($facts->description !== null) {
            $this->descriptions[mb_strtolower($facts->description)][] = $url;
        }
    }

    /**
     * @return list<string> the other pages with this title
     */
    public function sameTitle(string $url, ?string $title): array
    {
        return $title === null ? [] : array_values(array_diff($this->titles[mb_strtolower($title)] ?? [], [$url]));
    }

    /**
     * @return list<string> the other pages with this description
     */
    public function sameDescription(string $url, ?string $description): array
    {
        return $description === null ? [] : array_values(array_diff($this->descriptions[mb_strtolower($description)] ?? [], [$url]));
    }
}
