<?php

namespace JothamLec\MarketingToolkit\Sitemap;

use Illuminate\Support\Collection;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Collection as Collections;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Site;
use Statamic\Facades\Term as Terms;

/**
 * The URLs the sitemap lists. Which pages count is up to SiteSeo, whose
 * hooks a site may override (inSitemap(), termHasEntries(),
 * additionalSitemapUrls()): each is asked of the SiteSeo passed in.
 */
final class Sitemap
{
    /**
     * Every URL the sitemap lists, sorted by address, each with its other
     * languages (hreflang code => address) when it has any.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates?: array<string, string>}>
     */
    public function urls(SiteSeo $seo): Collection
    {
        return $this->entries($seo)
            ->merge($this->terms($seo))
            ->merge($seo->additionalSitemapUrls())
            ->unique('loc')
            ->sortBy('loc')
            ->values();
    }

    /**
     * The sites one sitemap lists: the current one, and the others on its
     * domain (languages under /fr/, /de/). A domain serves one sitemap.
     *
     * @return list<string>
     */
    public function sites(): array
    {
        $host = fn ($site) => strtolower((string) parse_url((string) $site->absoluteUrl(), PHP_URL_HOST));
        $current = $host(Site::current());

        return Site::all()->filter(fn ($site) => $host($site) === $current)->map->handle()->values()->all();
    }

    /**
     * The collections the sitemap and llms.txt list on $sites: those with a
     * route there, all of them or those `marketing-toolkit.sitemap.collections`
     * names, less `marketing-toolkit.sitemap.exclude_collections`.
     *
     * @param  list<string>  $sites
     * @return Collection<int, CollectionContract>
     */
    public function collections(array $sites): Collection
    {
        $only = config('marketing-toolkit.sitemap.collections');
        $excluded = (array) config('marketing-toolkit.sitemap.exclude_collections');

        return Collections::all()
            ->filter(fn (CollectionContract $collection) => collect($sites)->contains(fn (string $site) => $collection->route($site))
                && ($only === null || in_array($collection->handle(), (array) $only, true))
                && ! in_array($collection->handle(), $excluded, true))
            ->values();
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates?: array<string, string>}>
     */
    private function entries(SiteSeo $seo): Collection
    {
        $sites = $this->sites();
        $collections = $this->collections($sites)->map->handle()->all();

        if ($collections === []) {
            return collect();
        }

        return Entries::query()
            ->whereIn('collection', $collections)
            ->whereIn('site', $sites)
            ->whereStatus('published')
            // In chunks, keeping only the address and date: a big site's entries needn't all be in memory.
            ->orderBy('id')
            ->lazy(500)
            ->filter(fn (Entry $entry) => $seo->inSitemap($entry))
            ->map(fn (Entry $entry) => $this->row($seo, $entry))
            ->values()
            ->collect();
    }

    /**
     * The terms of the taxonomies `marketing-toolkit.sitemap.taxonomies`
     * names, on each site of the domain. Each site's terms are counted on
     * that site, as termHasEntries() asks about the current one.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates?: array<string, string>}>
     */
    private function terms(SiteSeo $seo): Collection
    {
        $rows = [];

        foreach ($this->sites() as $site) {
            Sites::as($site, function () use ($seo, $site, &$rows) {
                foreach ((array) config('marketing-toolkit.sitemap.taxonomies') as $taxonomy) {
                    foreach (Terms::query()->where('taxonomy', $taxonomy)->where('site', $site)->get() as $term) {
                        $term = $term->in($site);

                        if ($seo->inSitemap($term) && $seo->termHasEntries($term)) {
                            $rows[] = $this->row($seo, $term);
                        }
                    }
                }
            });
        }

        return collect($rows);
    }

    /**
     * @return array{loc: string, lastmod: ?string, alternates?: array<string, string>}
     */
    private function row(SiteSeo $seo, Entry|Term $content): array
    {
        $row = ['loc' => (string) $content->absoluteUrl(), 'lastmod' => $content->lastModified()?->toAtomString()];
        $alternates = $seo->contentAlternates($content);

        return $alternates === [] ? $row : [...$row, 'alternates' => $alternates];
    }
}
