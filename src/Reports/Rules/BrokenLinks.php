<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;

class BrokenLinks extends Rule
{
    public static function handle(): string
    {
        return 'broken_links';
    }

    public function label(): string
    {
        return 'Links within the site';
    }

    public function weight(): int
    {
        return 3;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        if ($page->brokenLinks !== []) {
            return Result::fail('Links to pages that don’t exist: '.implode(', ', array_slice($page->brokenLinks, 0, 5)).(count($page->brokenLinks) > 5 ? ' and more' : '').'.');
        }

        if ($page->redirectedLinks !== []) {
            return Result::warn('Links that go through a redirect (link to the new address instead): '.implode(', ', array_slice($page->redirectedLinks, 0, 5)).'.');
        }

        return Result::pass();
    }
}
