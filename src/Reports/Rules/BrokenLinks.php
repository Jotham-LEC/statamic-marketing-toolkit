<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\SiteFacts;

class BrokenLinks extends Rule
{
    public static function handle(): string
    {
        return 'broken_links';
    }

    public function weight(): int
    {
        return 3;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        if ($page->brokenLinks !== []) {
            return Result::fail('marketing-toolkit::reports.messages.links_broken', ['links' => $this->listed($page->brokenLinks, 5, paths: false)]);
        }

        if ($page->redirectedLinks !== []) {
            return Result::warn('marketing-toolkit::reports.messages.links_redirected', ['links' => $this->listed($page->redirectedLinks, 5, paths: false)]);
        }

        return Result::pass();
    }
}
