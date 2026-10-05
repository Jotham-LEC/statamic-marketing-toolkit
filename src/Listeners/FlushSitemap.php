<?php

namespace JothamLec\Seo\Listeners;

use Illuminate\Support\Facades\Cache;
use JothamLec\Seo\Http\Controllers\SitemapController;
use Statamic\Facades\Site;

/**
 * Any saved or deleted entry or term, or a moved page, can change which URLs
 * the sitemap lists or their dates.
 */
class FlushSitemap
{
    public function handle(): void
    {
        foreach (Site::all() as $site) {
            Cache::forget(SitemapController::CACHE_KEY.':'.$site->handle());
        }
    }
}
