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
        return 'seo::reports.rules.broken_links';
    }

    public function weight(): int
    {
        return 3;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        if ($page->brokenLinks !== []) {
            return Result::fail('seo::reports.messages.links_broken', ['links' => $this->listed($page->brokenLinks, 5, paths: false)]);
        }

        if ($page->redirectedLinks !== []) {
            return Result::warn('seo::reports.messages.links_redirected', ['links' => $this->listed($page->redirectedLinks, 5, paths: false)]);
        }

        return Result::pass();
    }
}
