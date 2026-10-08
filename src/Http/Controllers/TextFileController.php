<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Site;

/**
 * Serves /llms.txt and /ads.txt. The llms.txt file is cached like the sitemap
 * and forgotten with it (Listeners\FlushSitemap). A file of the same name in
 * public/ wins, because the web server serves it first.
 */
final class TextFileController
{
    private const string LLMS_CACHE_KEY = 'mt:llms';

    public function llms(Request $request, SiteSeo $seo): Response
    {
        throw_unless(Features::on('llms_txt'), NotFoundHttpException::class);

        $build = fn () => $seo->llmsTxt();

        // It is cached only on a host that the install names, because the addresses may come from
        // the Host header (Sites::trustsHost).
        return $this->text(Sites::trustsHost($request) ? Cache::remember(self::llmsCacheKey(Site::current()->handle()), SitemapController::cachedUntil(), $build) : $build());
    }

    public function ads(SiteSeo $seo): Response
    {
        throw_unless(Features::on('ads_txt'), NotFoundHttpException::class);

        $text = $seo->adsTxt();
        throw_if($text === null, NotFoundHttpException::class);

        return $this->text($text);
    }

    /**
     * Builds a key per site and per the settings that shape llms.txt (the
     * collections the sitemap lists, and where descriptions come from), so a
     * deploy that changes them doesn't serve the old file until the next save.
     */
    public static function llmsCacheKey(string $site): string
    {
        return self::LLMS_CACHE_KEY.':'.$site.':'.md5(serialize([
            config('marketing-toolkit.sitemap'), config('marketing-toolkit.collections'), config('marketing-toolkit.llms_txt'),
        ]));
    }

    private function text(string $body): Response
    {
        return response($body)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
