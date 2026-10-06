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
        return 'seo::reports.rules.canonical';
    }

    public function weight(): int
    {
        return 3;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        if ($page->canonical === null) {
            return Result::fail('seo::reports.messages.canonical_missing');
        }

        if (! preg_match('#^https?://#i', $page->canonical)) {
            return Result::fail('seo::reports.messages.canonical_relative', ['url' => $page->canonical]);
        }

        if (rtrim($page->canonical, '/') !== rtrim($url, '/')) {
            return Result::pass('seo::reports.messages.canonical_elsewhere', ['url' => $page->canonical]);
        }

        return Result::pass();
    }
}
