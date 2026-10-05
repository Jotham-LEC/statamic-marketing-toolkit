<?php

namespace JothamLec\Seo\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JothamLec\Seo\SiteSeo;
use Statamic\Facades\Site;

/**
 * /sitemap.xml: every published page search engines should index. Up to
 * `seo.sitemap.per_page` URLs it is one <urlset>; past that it becomes an
 * index of /sitemap_{n}.xml. Cached until an entry, term or tree is saved
 * (JothamLec\Seo\Listeners\FlushSitemap).
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

    public function page(int $page): Response
    {
        $chunk = $this->urls()->forPage($page, $this->perPage());

        abort_if($page < 1 || $chunk->isEmpty(), 404);

        return $this->xml(view('seo::sitemap', ['urls' => $chunk])->render());
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: ?string}>
     */
    private function urls(): Collection
    {
        return Cache::rememberForever(
            self::CACHE_KEY.':'.Site::current()->handle(),
            fn () => app(SiteSeo::class)->sitemapUrls(),
        );
    }

    private function perPage(): int
    {
        return max(1, (int) config('seo.sitemap.per_page', 1000));
    }

    private function xml(string $body): Response
    {
        return new Response($body, 200, ['Content-Type' => 'text/xml; charset=UTF-8']);
    }
}
