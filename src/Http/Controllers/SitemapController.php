<?php

namespace JothamLec\MarketingToolkit\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Site;

/**
 * /sitemap.xml: every published page search engines should index. Up to
 * `marketing-toolkit.sitemap.per_page` URLs it is one <urlset>; past that it becomes an
 * index of /sitemap_{n}.xml. Cached until an entry, term, tree, collection
 * or taxonomy is saved (JothamLec\MarketingToolkit\Listeners\FlushSitemap).
 */
class SitemapController
{
    public const string CACHE_KEY = 'mt:sitemap';

    public function index(): Response
    {
        $urls = $this->urls();
        $perPage = $this->perPage();

        if ($urls->count() <= $perPage) {
            return $this->xml(view('marketing-toolkit::sitemap', ['urls' => $urls])->render());
        }

        $pages = range(1, (int) ceil($urls->count() / $perPage));

        return $this->xml(view('marketing-toolkit::sitemap-index', [
            'pages' => array_map(fn (int $page) => app(SiteSeo::class)->absolute(route('mt.sitemap.page', ['page' => $page], false)), $pages),
        ])->render());
    }

    public function page(string $page): Response
    {
        // Taken as text: a number too big for an int would fail the type, a 500 rather than a 404.
        throw_if(strlen($page) > 9 || (int) $page < 1, NotFoundHttpException::class);

        $chunk = $this->urls()->forPage((int) $page, $this->perPage());

        throw_if($chunk->isEmpty(), NotFoundHttpException::class);

        return $this->xml(view('marketing-toolkit::sitemap', ['urls' => $chunk])->render());
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates?: array<string, string>}>
     */
    private function urls(): Collection
    {
        throw_unless(Features::on('sitemap'), NotFoundHttpException::class);

        $build = fn () => app(SiteSeo::class)->sitemapUrls();

        // Cached only on a host the install names: the addresses may come from the Host header (Sites::trustsHost).
        return Sites::trustsHost(request()) ? Cache::rememberForever(self::cacheKey(Site::current()->handle()), $build) : $build();
    }

    /**
     * Per site, and per `marketing-toolkit.sitemap` and `.hreflang` settings
     * (the rows carry each page's other languages), so a deploy or a switch
     * under Features that changes them doesn't serve the old list until the
     * next save.
     */
    public static function cacheKey(string $site): string
    {
        return self::CACHE_KEY.':'.$site.':'.md5(serialize([config('marketing-toolkit.sitemap'), config('marketing-toolkit.hreflang')]));
    }

    private function perPage(): int
    {
        return max(1, (int) config('marketing-toolkit.sitemap.per_page'));
    }

    private function xml(string $body): Response
    {
        return new Response($body, 200, ['Content-Type' => 'text/xml; charset=UTF-8']);
    }
}
