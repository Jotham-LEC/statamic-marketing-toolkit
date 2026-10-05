<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;

class DescriptionLength extends Rule
{
    public static function handle(): string
    {
        return 'description_length';
    }

    public function label(): string
    {
        return 'Description length';
    }

    public function weight(): int
    {
        return 2;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        if ($page->description === null) {
            return Result::fail('No meta description; search engines will pick text from the page.');
        }

        $length = mb_strlen($page->description);
        [$min, $max] = [$site->settings->int('description_min'), $site->settings->int('description_max')];

        return match (true) {
            $length < $min => Result::warn("{$length} characters; aim for {$min}–{$max}."),
            $length > $max => Result::warn("{$length} characters; aim for {$min}–{$max}. Longer ones are cut off."),
            default => Result::pass("{$length} characters."),
        };
    }
}
