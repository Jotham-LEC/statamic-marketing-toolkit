<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Facades\Site;

/**
 * /llms.txt and /ads.txt. llms.txt is cached like the sitemap, and
 * forgotten with it (Listeners\FlushSitemap). A file of the same name in
 * public/ wins: the route isn't registered.
 */
class TextFileController
{
    public const string LLMS_CACHE_KEY = 'seo:llms';

    public function llms(SiteSeo $seo): Response
    {
        abort_unless(Sites::served(), 404); // Free: the default site's domain only.

        return $this->text(Cache::rememberForever(self::llmsCacheKey(Site::current()->handle()), fn () => $seo->llmsTxt()));
    }

    public function ads(SiteSeo $seo): Response
    {
        abort_unless(Sites::served(), 404);

        $text = $seo->adsTxt();
        abort_if($text === null, 404);

        return $this->text($text);
    }

    public static function llmsCacheKey(string $site): string
    {
        return self::LLMS_CACHE_KEY.':'.$site;
    }

    private function text(string $body): Response
    {
        return new Response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
