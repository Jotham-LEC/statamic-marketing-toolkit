<?php

namespace JothamLec\MarketingToolkit\Reports\Rules;

use JothamLec\MarketingToolkit\Reports\PageFacts;
use JothamLec\MarketingToolkit\Reports\Result;
use JothamLec\MarketingToolkit\Reports\SiteFacts;

class ImageAlt extends Rule
{
    public static function handle(): string
    {
        return 'image_alt';
    }

    public function label(): string
    {
        return 'seo::reports.rules.image_alt';
    }

    public function weight(): int
    {
        return 1;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        $missing = $page->imagesWithoutAlt;

        return match (true) {
            $missing === 0 => Result::pass(),
            $missing * 2 > $page->images => Result::fail('seo::reports.messages.images_without_alt', ['count' => $missing, 'total' => $page->images]),
            default => Result::warn('seo::reports.messages.images_without_alt', ['count' => $missing, 'total' => $page->images]),
        };
    }
}
