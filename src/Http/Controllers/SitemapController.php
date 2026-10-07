<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Site;

/**
 * /sitemap.xml: every published page search engines should index. Up to
 * `seo.sitemap.per_page` URLs it is one <urlset>; past that it becomes an
 * index of /sitemap_{n}.xml. Cached until an entry, term, tree, collection
 * or taxonomy is saved (JothamLec\MarketingToolkit\Listeners\FlushSitemap).
 */
class SitemapController
{
    public const string CACHE_KEY = 'seo:sitemap';

    public function index(): Response
    {
        $urls = $this->urls();
        $perPage = $this->perPage();

        if ($urls->count() <= $perPage) {
            return $this->xml(view('seo::sitemap', ['urls' => $urls])->render());
        }

        $pages = range(1, (int) ceil($urls->count() / $perPage));

        return $this->xml(view('seo::sitemap-index', [
            'pages' => array_map(fn (int $page) => app(SiteSeo::class)->absolute(route('seo.sitemap.page', ['page' => $page], false)), $pages),
        ])->render());
    }

    public function page(string $page): Response
    {
        // Taken as text: a number too big for an int would fail the type, a 500 rather than a 404.
        throw_if(strlen($page) > 9 || (int) $page < 1, NotFoundHttpException::class);

        $chunk = $this->urls()->forPage((int) $page, $this->perPage());

        throw_if($chunk->isEmpty(), NotFoundHttpException::class);

        return $this->xml(view('seo::sitemap', ['urls' => $chunk])->render());
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates?: array<string, string>}>
     */
    private function urls(): Collection
    {
        // Off in the config or under Features; Free: the default site's domain only.
        throw_unless(config('seo.sitemap.enabled') && Sites::served(), NotFoundHttpException::class);

        return Cache::rememberForever(self::cacheKey(Site::current()->handle()), fn () => app(SiteSeo::class)->sitemapUrls());
    }

    /**
     * Per site, and per `seo.sitemap` settings, so a deploy that changes
     * them doesn't serve the old list until the next save.
     */
    public static function cacheKey(string $site): string
    {
        return self::CACHE_KEY.':'.$site.':'.md5(serialize(config('seo.sitemap')));
    }

    private function perPage(): int
    {
        return max(1, (int) config('seo.sitemap.per_page'));
    }

    private function xml(string $body): Response
    {
        return new Response($body, 200, ['Content-Type' => 'text/xml; charset=UTF-8']);
    }
}
