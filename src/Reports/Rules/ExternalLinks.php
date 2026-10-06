<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;

/**
 * Links to other sites that lead nowhere (a 404, a 410, a domain that no
 * longer exists). Off by default: checking means requests to those sites.
 */
class ExternalLinks extends Rule
{
    public static function handle(): string
    {
        return 'external_links';
    }

    public function label(): string
    {
        return 'Links to other sites';
    }

    public function weight(): int
    {
        return 1;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        if ($page->brokenExternalLinks === []) {
            return Result::pass();
        }

        return Result::fail('Links to other sites that lead nowhere: '.implode(', ', array_slice($page->brokenExternalLinks, 0, 5)).(count($page->brokenExternalLinks) > 5 ? ' and more' : '').'.');
    }
}
