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
 * This class collects the URLs the sitemap lists. SiteSeo decides which pages
 * count through hooks that a site may override (inSitemap(), termHasEntries(),
 * and additionalSitemapUrls()), and each hook is called on the SiteSeo passed in.
 */
final class Sitemap
{
    /**
     * Returns every URL the sitemap lists, sorted by address. Each URL carries
     * its other languages (hreflang code => address) when it has any.
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
     * Returns the sites one sitemap lists, which are the current site and the
     * others on its domain (languages under /fr/ or /de/), because a domain
     * serves one sitemap.
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
     * Returns the collections the sitemap and llms.txt list on $sites. These
     * are the collections with a route there, either all of them or those that
     * `marketing-toolkit.sitemap.collections` names, minus those in
     * `marketing-toolkit.sitemap.exclude_collections`.
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
            // Entries are read in chunks with only the address and date kept, so not all of them sit in memory.
            ->orderBy('id')
            ->lazy(500)
            ->filter(fn (Entry $entry) => $seo->inSitemap($entry))
            ->map(fn (Entry $entry) => $this->row($seo, $entry))
            ->values()
            ->collect();
    }

    /**
     * Returns the terms of the taxonomies that `marketing-toolkit.sitemap.taxonomies`
     * names, on each site of the domain. Each site's terms are counted on
     * that site, because termHasEntries() asks about the current one.
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
