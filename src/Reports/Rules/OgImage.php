<?php

namespace JothamLec\Seo\Reports\Rules;

use JothamLec\Seo\Reports\PageFacts;
use JothamLec\Seo\Reports\Result;
use JothamLec\Seo\Reports\SiteFacts;

class OgImage extends Rule
{
    public static function handle(): string
    {
        return 'og_image';
    }

    public function label(): string
    {
        return 'Share image';
    }

    public function weight(): int
    {
        return 1;
    }

    public function check(string $url, PageFacts $page, SiteFacts $site): Result
    {
        return $page->ogImage === null
            ? Result::fail('No og:image: links shared on social media show no picture.')
            : Result::pass();
    }
}
