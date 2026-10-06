<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;

/**
 * Links to pages of this site that don't exist, or that a redirect answers.
 */
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

    public function check(string $url, PageFacts $page): Result
    {
        if ($page->brokenLinks !== []) {
            return Result::fail('seo::reports.messages.links_broken', ['links' => $this->listed($page->brokenLinks)]);
        }

        if ($page->redirectedLinks !== []) {
            return Result::warn('seo::reports.messages.links_redirected', ['links' => $this->listed($page->redirectedLinks)]);
        }

        return Result::pass();
    }

    public function appliesToNoindex(): bool
    {
        return true;
    }
}
