<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\SiteFacts;

class SingleH1 extends Rule
{
    public static function handle(): string
    {
        return 'single_h1';
    }

    public function weight(): int
    {
        return 2;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        return match (count($page->h1s)) {
            0 => Result::fail('marketing-toolkit::reports.messages.h1_missing'),
            1 => Result::pass(),
            default => Result::warn('marketing-toolkit::reports.messages.h1_many', ['count' => count($page->h1s)]),
        };
    }
}
