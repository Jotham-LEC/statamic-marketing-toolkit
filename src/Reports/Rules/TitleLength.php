<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;

class TitleLength extends Rule
{
    public static function handle(): string
    {
        return 'title_length';
    }

    public function label(): string
    {
        return 'Title length';
    }

    public function weight(): int
    {
        return 2;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        if ($page->title === null) {
            return Result::fail('No <title>.');
        }

        $length = mb_strlen($page->title);
        [$min, $max] = [$site->settings->int('title_min'), $site->settings->int('title_max')];

        return match (true) {
            $length < $min => Result::warn("{$length} characters; aim for {$min}–{$max}. Short titles waste the space search results give them."),
            $length > $max => Result::warn("{$length} characters; aim for {$min}–{$max}. Search results cut longer titles off."),
            default => Result::pass("{$length} characters."),
        };
    }
}
