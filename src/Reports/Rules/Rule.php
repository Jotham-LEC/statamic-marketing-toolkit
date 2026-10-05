<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;

/**
 * One check a report runs on every page. Its weight is how much it counts
 * towards the page's score: 3 for what keeps a page out of search results,
 * 2 for what shapes how it shows, 1 for polish.
 */
abstract class Rule
{
    /**
     * The key in the addon settings (`rule_{handle}`) and in a page's results.
     */
    abstract public static function handle(): string;

    abstract public function label(): string;

    abstract public function weight(): int;

    abstract public function check(string $url, PageFacts $page, SiteFacts $site): Result;

    /**
     * Whether the check applies to a page search engines are told to skip.
     */
    public function appliesToNoindex(): bool
    {
        return false;
    }

    /**
     * @param  list<string>  $urls
     */
    protected function list(array $urls, int $show = 3): string
    {
        $paths = array_map(fn ($url) => parse_url($url, PHP_URL_PATH) ?: '/', $urls);
        $more = count($paths) - $show;

        return implode(', ', array_slice($paths, 0, $show)).($more > 0 ? " and {$more} more" : '');
    }
}
