<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\SiteFacts;

/**
 * Flags links to other sites that lead nowhere, such as a 404, a 410, or a
 * domain that no longer exists. The check is off by default, because it sends
 * requests to those sites.
 */
class ExternalLinks extends Rule
{
    public static function handle(): string
    {
        return 'external_links';
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

        return Result::fail('marketing-toolkit::reports.messages.external_links_broken', ['links' => $this->listed($page->brokenExternalLinks, 5, paths: false)]);
    }
}
