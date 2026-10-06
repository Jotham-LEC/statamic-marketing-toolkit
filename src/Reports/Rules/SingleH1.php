<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;

class SingleH1 extends Rule
{
    public static function handle(): string
    {
        return 'single_h1';
    }

    public function label(): string
    {
        return 'seo::reports.rules.single_h1';
    }

    public function weight(): int
    {
        return 2;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        return match (count($page->h1s)) {
            0 => Result::fail('seo::reports.messages.h1_missing'),
            1 => Result::pass(),
            default => Result::warn('seo::reports.messages.h1_many', ['count' => count($page->h1s)]),
        };
    }
}
