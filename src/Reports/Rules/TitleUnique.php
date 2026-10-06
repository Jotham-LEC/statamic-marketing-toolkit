<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\SiteFacts;

class TitleUnique extends Rule
{
    public static function handle(): string
    {
        return 'title_unique';
    }

    public function weight(): int
    {
        return 2;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        $others = $site->sameTitle($url, $page->title);

        return $others === []
            ? Result::pass()
            : Result::fail('seo::reports.messages.title_same', ['pages' => $this->listed($others)]);
    }
}
