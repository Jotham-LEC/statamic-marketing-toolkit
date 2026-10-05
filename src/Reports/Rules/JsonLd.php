<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;

class JsonLd extends Rule
{
    public static function handle(): string
    {
        return 'json_ld';
    }

    public function label(): string
    {
        return 'Structured data';
    }

    public function weight(): int
    {
        return 2;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        return match (true) {
            $page->jsonLdErrors !== [] => Result::fail('JSON-LD that doesn’t parse: '.implode('; ', $page->jsonLdErrors).'.'),
            $page->jsonLd === 0 => Result::warn('No JSON-LD structured data.'),
            default => Result::pass(),
        };
    }
}
