<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\SiteFacts;

class OgImage extends Rule
{
    public static function handle(): string
    {
        return 'og_image';
    }

    public function label(): string
    {
        return 'seo::reports.rules.og_image';
    }

    public function weight(): int
    {
        return 1;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        return $page->ogImage === null
            ? Result::fail('seo::reports.messages.og_image_missing')
            : Result::pass();
    }
}
