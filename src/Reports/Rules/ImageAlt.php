<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;

class ImageAlt extends Rule
{
    public static function handle(): string
    {
        return 'image_alt';
    }

    public function label(): string
    {
        return 'Image descriptions';
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
            $missing * 2 > $page->images => Result::fail("{$missing} of {$page->images} images have no alt text."),
            default => Result::warn("{$missing} of {$page->images} images have no alt text."),
        };
    }
}
