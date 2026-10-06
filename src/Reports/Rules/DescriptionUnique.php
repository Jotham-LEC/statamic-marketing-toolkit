<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;

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
