<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;

class NoindexInSitemap extends Rule
{
    public static function handle(): string
    {
        return 'noindex_in_sitemap';
    }

    public function label(): string
    {
        return 'seo::reports.rules.noindex_in_sitemap';
    }

    public function weight(): int
    {
        return 3;
    }

    public function appliesToNoindex(): bool
    {
        return true;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        return $page->noindex() && $page->inSitemap
            ? Result::fail('seo::reports.messages.noindex_in_sitemap')
            : Result::pass();
    }
}
