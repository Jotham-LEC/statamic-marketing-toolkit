<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\SiteFacts;

class JsonLd extends Rule
{
    public static function handle(): string
    {
        return 'json_ld';
    }

    public function weight(): int
    {
        return 2;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        return match (true) {
            $page->jsonLdErrors !== [] => Result::fail('marketing-toolkit::reports.messages.json_ld_invalid', ['errors' => implode('; ', $page->jsonLdErrors)]),
            $page->jsonLd === 0 => Result::warn('marketing-toolkit::reports.messages.json_ld_missing'),
            default => Result::pass(),
        };
    }
}
