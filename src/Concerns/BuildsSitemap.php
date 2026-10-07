<?php

namespace JothamLec\MarketingToolkit\Concerns;

use Illuminate\Support\Collection;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Site;

/**
 * The URLs the sitemap lists.
 *
 * @phpstan-require-extends SiteSeo
 */
trait BuildsSitemap
{
    /**
     * Every URL the sitemap lists, sorted by address, each with its other
     * languages (hreflang code => address) when it has any.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates?: array<string, string>}>
     */
    public function sitemapUrls(): Collection
    {
        return $this->sitemapEntries()
            ->merge($this->sitemapTerms())
            ->merge($this->additionalSitemapUrls())
            ->unique('loc')
            ->sortBy('loc')
            ->values();
    }

    /**
     * URLs that are not entries or terms (controller pages). Empty by default.
     *
     * @return list<array{loc: string, lastmod: ?string}>
     *
     * @api
     */
    public function additionalSitemapUrls(): array
    {
        return [];
    }

    /** @api */
    public function inSitemap(Entry|Term $content): bool
    {
        // A translation's own group, else its origin's.
        $seo = $content->value('seo');
        $seo = is_array($seo) ? $seo : [];
        $canonical = $seo['canonical'] ?? null;

        // Only canonical addresses: a page that names another (here or on another site) as its canonical is left out.
        return $content->url() !== null
            && ! ($content instanceof Entry && $content->isRedirect())
            && ! ($seo['noindex'] ?? false)
            && ($seo['sitemap'] ?? true) !== false
            && ! $this->isProtected($content)
            && (blank($canonical) || rtrim((string) $canonical, '/') === rtrim((string) $content->absoluteUrl(), '/'));
    }

    /**
     * The sites one sitemap lists: the current one, and the others on its
     * domain (languages under /fr/, /de/). A domain serves one sitemap.
     *
     * @return list<string>
     */
    public function sitemapSites(): array
    {
        $host = fn ($site) => strtolower((string) parse_url((string) $site->absoluteUrl(), PHP_URL_HOST));
        $current = $host(Site::current());

        return Site::all()->filter(fn ($site) => $host($site) === $current)->map->handle()->values()->all();
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates?: array<string, string>}>
     */
    protected function sitemapEntries(): Collection
    {
        $sites = $this->sitemapSites();
        $collections = $this->sitemapCollections($sites)->map->handle()->all();

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
            ->filter(fn (Entry $entry) => $this->inSitemap($entry))
            ->map(fn (Entry $entry) => $this->sitemapRow($entry))
            ->values()
            ->collect();
    }

    /**
     * The collections the sitemap and llms.txt list on $sites: those with a
     * route there, all of them or those `marketing-toolkit.sitemap.collections` names, less
     * `marketing-toolkit.sitemap.exclude_collections`.
     *
     * @param  list<string>  $sites
     * @return Collection<int, \Statamic\Contracts\Entries\Collection>
     */
    protected function sitemapCollections(array $sites): Collection
    {
        $only = config('marketing-toolkit.sitemap.collections');
        $excluded = (array) config('marketing-toolkit.sitemap.exclude_collections');

        return \Statamic\Facades\Collection::all()
            ->filter(fn ($collection) => collect($sites)->contains(fn (string $site) => $collection->route($site))
                && ($only === null || in_array($collection->handle(), (array) $only, true))
                && ! in_array($collection->handle(), $excluded, true))
            ->values();
    }

    /**
     * Whether a term has published entries, so its page is worth listing and
     * checking. Statamic counts only entries of the collections the taxonomy
     * is attached to; override for one that isn't attached (the entries name
     * their terms in a `terms` field), or to count only some entries. Only
     * the current site's entries count (Statamic counts every site's): the
     * sitemap and a report each ask on the site they are of.
     *
     * @api
     */
    public function termHasEntries(Term $term): bool
    {
        return $term->queryEntries()->where('site', Site::current()->handle())->whereStatus('published')->count() > 0;
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates?: array<string, string>}>
     */
    protected function sitemapTerms(): Collection
    {
        // Each site's terms counted on that site: termHasEntries() asks of the current one.
        return collect($this->sitemapSites())->flatMap(fn (string $site) => Sites::as($site, fn () => collect((array) config('marketing-toolkit.sitemap.taxonomies'))
            ->flatMap(fn (string $taxonomy): Collection => \Statamic\Facades\Term::query()->where('taxonomy', $taxonomy)->where('site', $site)->get())
            ->map(fn (Term $term): Term => $term->in($site))
            ->filter(fn (Term $term) => $this->inSitemap($term) && $this->termHasEntries($term))
            ->map(fn (Term $term) => $this->sitemapRow($term))
            ->values()
            ->all()));
    }

    /**
     * @return array{loc: string, lastmod: ?string, alternates?: array<string, string>}
     */
    protected function sitemapRow(Entry|Term $content): array
    {
        $row = ['loc' => (string) $content->absoluteUrl(), 'lastmod' => $content->lastModified()?->toAtomString()];
        $alternates = $this->contentAlternates($content);

        return $alternates === [] ? $row : [...$row, 'alternates' => $alternates];
    }
}
