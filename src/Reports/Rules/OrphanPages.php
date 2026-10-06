<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\SiteFacts;
use JothamLec\MarketingToolkit\Support\Uris;

/**
 * A page in the sitemap that no other page links to: search engines find it
 * only through the sitemap, and visitors only through search. The home page
 * needs no link.
 */
class OrphanPages extends Rule
{
    public static function handle(): string
    {
        return 'orphan_pages';
    }

    public function weight(): int
    {
        return 2;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        if (! $page->inSitemap || Uris::normalizePath($url) === '/' || $site->linkedFrom($url) !== []) {
            return Result::pass();
        }

        return Result::warn('seo::reports.messages.orphan');
    }
}
