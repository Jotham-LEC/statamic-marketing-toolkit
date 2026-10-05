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
        return 'One main heading';
    }

    public function weight(): int
    {
        return 2;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        return match (count($page->h1s)) {
            0 => Result::fail('No <h1>: the page has no main heading.'),
            1 => Result::pass(),
            default => Result::warn(count($page->h1s).' <h1> headings; keep one for the page’s main heading.'),
        };
    }
}
