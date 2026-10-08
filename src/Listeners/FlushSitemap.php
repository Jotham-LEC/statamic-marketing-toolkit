<?php

namespace JothamLec\MarketingToolkit\Listeners;

use Illuminate\Support\Facades\Cache;
use JothamLec\MarketingToolkit\Http\Controllers\SitemapController;
use JothamLec\MarketingToolkit\Http\Controllers\TextFileController;
use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Facades\Site;

/**
 * This listener flushes the cached sitemap and llms.txt. A saved or deleted entry or term, a moved page,
 * an entry whose scheduled date arrives, or a collection or taxonomy with a new route can change which
 * URLs the sitemap lists or their dates, and so can a deploy, which clears the Stache. llms.txt also
 * begins with Brand's description, so a saved global set flushes them too.
 */
final class FlushSitemap
{
    public function handle(): void
    {
        if (! Features::on('sitemap') && ! Features::on('llms_txt')) {
            return;
        }

        foreach (Site::all() as $site) {
            Cache::forget(SitemapController::cacheKey($site->handle()));
            Cache::forget(TextFileController::llmsCacheKey($site->handle()));
        }
    }
}
