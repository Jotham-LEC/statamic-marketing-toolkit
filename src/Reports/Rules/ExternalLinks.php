<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;

/**
 * Links to other sites that lead nowhere (a 404, a 410, a domain that no
 * longer exists). `seo.reports.external_links` turns it off.
 */
class ExternalLinks extends Rule
{
    public static function handle(): string
    {
        return 'external_links';
    }

    public function label(): string
    {
        return 'seo::reports.rules.external_links';
    }

    public function check(string $url, PageFacts $page): Result
    {
        return $page->brokenExternalLinks === []
            ? Result::pass()
            : Result::fail('seo::reports.messages.external_links_broken', ['links' => $this->listed($page->brokenExternalLinks)]);
    }

    public function appliesToNoindex(): bool
    {
        return true;
    }
}
