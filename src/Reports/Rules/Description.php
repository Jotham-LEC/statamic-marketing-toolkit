<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;

/**
 * A page with no description: Google writes its own snippet from the page.
 */
class Description extends Rule
{
    public static function handle(): string
    {
        return 'description';
    }

    public function label(): string
    {
        return 'seo::reports.rules.description';
    }

    public function check(string $url, PageFacts $page): Result
    {
        return $page->description === null ? Result::fail('seo::reports.messages.description_missing') : Result::pass();
    }
}
