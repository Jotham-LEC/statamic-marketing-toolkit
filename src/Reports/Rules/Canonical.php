<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;

class Canonical extends Rule
{
    public static function handle(): string
    {
        return 'canonical';
    }

    public function label(): string
    {
        return 'Canonical address';
    }

    public function weight(): int
    {
        return 3;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        if ($page->canonical === null) {
            return Result::fail('No canonical link: search engines may split this page’s ranking across its addresses.');
        }

        if (! preg_match('#^https?://#i', $page->canonical)) {
            return Result::fail("The canonical link isn’t a full address: {$page->canonical}.");
        }

        if (rtrim($page->canonical, '/') !== rtrim($url, '/')) {
            return Result::pass("Points elsewhere: {$page->canonical}. Right for a piece first published there.");
        }

        return Result::pass();
    }
}
