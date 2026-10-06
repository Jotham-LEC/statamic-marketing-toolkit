<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\SiteFacts;

class DescriptionUnique extends Rule
{
    public static function handle(): string
    {
        return 'description_unique';
    }

    public function label(): string
    {
        return 'seo::reports.rules.description_unique';
    }

    public function weight(): int
    {
        return 2;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        $others = $site->sameDescription($url, $page->description);

        return $others === []
            ? Result::pass()
            : Result::fail('seo::reports.messages.description_same', ['pages' => $this->listed($others)]);
    }
}
