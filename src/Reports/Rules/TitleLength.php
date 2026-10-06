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
        return 'seo::reports.rules.title_length';
    }

    public function weight(): int
    {
        return 2;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        if ($page->title === null) {
            return Result::fail('seo::reports.messages.title_missing');
        }

        $length = mb_strlen($page->title);
        [$min, $max] = [$site->settings->int('title_min'), $site->settings->int('title_max')];

        return match (true) {
            $length < $min => Result::warn('seo::reports.messages.title_short', ['count' => $length, 'min' => $min, 'max' => $max]),
            $length > $max => Result::warn('seo::reports.messages.title_long', ['count' => $length, 'min' => $min, 'max' => $max]),
            default => Result::pass('seo::reports.messages.characters', ['count' => $length]),
        };
    }
}
