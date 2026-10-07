<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\SiteFacts;

class NoindexInSitemap extends Rule
{
    public static function handle(): string
    {
        return 'noindex_in_sitemap';
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
            ? Result::fail('marketing-toolkit::reports.messages.noindex_in_sitemap')
            : Result::pass();
    }
}
