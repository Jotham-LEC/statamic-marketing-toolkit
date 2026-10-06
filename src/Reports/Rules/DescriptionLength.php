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
        return 'seo::reports.rules.description_length';
    }

    public function weight(): int
    {
        return 2;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        if ($page->description === null) {
            return Result::fail('seo::reports.messages.description_missing');
        }

        $length = mb_strlen($page->description);
        [$min, $max] = [$site->settings->int('description_min'), $site->settings->int('description_max')];

        return match (true) {
            $length < $min => Result::warn('seo::reports.messages.description_short', ['count' => $length, 'min' => $min, 'max' => $max]),
            $length > $max => Result::warn('seo::reports.messages.description_long', ['count' => $length, 'min' => $min, 'max' => $max]),
            default => Result::pass('seo::reports.messages.characters', ['count' => $length]),
        };
    }
}
