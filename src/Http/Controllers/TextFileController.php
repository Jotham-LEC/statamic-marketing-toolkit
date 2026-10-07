<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use JothamLec\MarketingToolkit\SiteSeo;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Site;

/**
 * /llms.txt and /ads.txt. llms.txt is cached like the sitemap, and
 * forgotten with it (Listeners\FlushSitemap). A file of the same name in
 * public/ wins: the web server serves it first.
 */
class TextFileController
{
    public const string LLMS_CACHE_KEY = 'mt:llms';

    public function llms(SiteSeo $seo): Response
    {
        // Off in the config or under Features.
        throw_unless(config('marketing-toolkit.llms_txt.enabled'), NotFoundHttpException::class);

        return $this->text(Cache::rememberForever(self::llmsCacheKey(Site::current()->handle()), fn () => $seo->llmsTxt()));
    }

    public function ads(SiteSeo $seo): Response
    {
        throw_unless(config('marketing-toolkit.ads_txt.enabled'), NotFoundHttpException::class);

        $text = $seo->adsTxt();
        throw_if($text === null, NotFoundHttpException::class);

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
