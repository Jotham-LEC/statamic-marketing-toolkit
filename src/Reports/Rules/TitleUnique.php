<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;

class TitleUnique extends Rule
{
    public static function handle(): string
    {
        return 'title_unique';
    }

    public function label(): string
    {
        return 'Unique title';
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
            : Result::fail('Same title as '.$this->list($others).'.');
    }
}
