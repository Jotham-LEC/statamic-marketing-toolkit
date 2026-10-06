<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;
use JothamLec\Seo\Support\Uris;

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

    public function label(): string
    {
        return 'Linked from another page';
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

        return Result::warn('No other page links here; link to it from a related page.');
    }
}
