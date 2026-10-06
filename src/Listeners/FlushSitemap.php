<?php

namespace JothamLec\MarketingToolkit\Listeners;

use Illuminate\Support\Facades\Cache;
use JothamLec\MarketingToolkit\Http\Controllers\SitemapController;
use JothamLec\MarketingToolkit\Http\Controllers\TextFileController;
use Statamic\Facades\Site;

/**
 * The sitemap and llms.txt. Any saved or deleted entry or term, a moved page, an entry whose scheduled
 * date arrives, or a collection or taxonomy given a new route can change
 * which URLs the sitemap lists or their dates; so can a deploy, which
 * clears the Stache.
 */
class FlushSitemap
{
    public function handle(): void
    {
        foreach (Site::all() as $site) {
            Cache::forget(SitemapController::cacheKey($site->handle()));
            Cache::forget(TextFileController::llmsCacheKey($site->handle()));
        }
    }
}
