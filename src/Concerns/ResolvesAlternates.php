<?php

namespace JothamLec\MarketingToolkit\Concerns;

use Illuminate\Support\Str;
use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;

/**
 * A page's other languages, for hreflang and og:locale. Part of SiteSeo's
 * override surface: a project overrides these methods on its SiteSeo
 * subclass (config `seo.class`), not on the trait, which only splits the
 * class into readable parts.
 *
 * @phpstan-require-extends SiteSeo
 */
trait ResolvesAlternates
{
    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    */

    /**
     * The same page in each language, for hreflang: code => address, with
     * `x-default` for the version shown to everyone else (config
     * `seo.hreflang.x_default`). Empty when there is no other language to
     * point to, or when this isn't an address to index: noindexed, canonical
     * elsewhere, a listing past its first page.
     *
     * @return array<string, string>
     */
    public function alternates(Context $context): array
    {
        return $this->once($context, 'alternates', function () use ($context) {
            $content = $context->content();

            if (! $content || $context->page() > 1 || str_contains($this->robots($context), 'noindex')) {
                return [];
            }

            if (rtrim((string) $this->canonical($context), '/') !== rtrim($this->url($context), '/')) {
                return [];
            }

            return $this->contentAlternates($content);
        });
    }

    /**
     * hreflang code => address of $content in each language it is published
     * and listed in, itself included, plus `x-default`. Empty under two.
     *
     * @return array<string, string>
     */
    public function contentAlternates(Entry|Term $content): array
    {
        if (! config('seo.hreflang.enabled', true) || ! Sites::multiple()) {
            return [];
        }

        $versions = $this->localizations($content);

        if (! isset($versions[$content->locale()])) {
            return [];
        }

        $codes = $this->hreflangCodes();
        $alternates = [];

        foreach ($versions as $site => $version) {
            $alternates[$codes[$site]] ??= (string) $version->absoluteUrl();
        }

        if (count($alternates) < 2) {
            return [];
        }

        $default = $this->xDefaultSite();

        if ($default !== null && isset($versions[$default])) {
            $alternates['x-default'] = (string) $versions[$default]->absoluteUrl();
        }

        return $alternates;
    }

    /**
     * $content on each site it can be listed on, in the sites' order: an
     * entry's origin and its localizations, a term on each of its
     * taxonomy's sites (where it has entries). Drafts, noindexed versions
     * and those canonical elsewhere are left out, as from the sitemap.
     *
     * @return array<string, Entry|Term> site handle => content
     */
    public function localizations(Entry|Term $content): array
    {
        if ($content instanceof Entry) {
            $root = $content->root();
            $versions = collect([$root, ...$root->descendants()->values()->all()])
                ->filter(fn (Entry $entry) => $entry->status() === 'published');
        } else {
            $versions = collect($content->taxonomy()?->sites() ?? [])
                ->map(fn (string $site) => $content->in($site))
                ->filter(fn (Term $term) => Sites::as($term->locale(), fn () => $this->termHasEntries($term)));
        }

        $versions = $versions->filter(fn (Entry|Term $version) => $this->inSitemap($version))
            ->keyBy(fn (Entry|Term $version) => $version->locale());

        return collect(Sites::handles())
            ->filter(fn (string $site) => $versions->has($site))
            ->mapWithKeys(fn (string $site) => [$site => $versions->get($site)])
            ->all();
    }

    /**
     * Each site's hreflang code: its language (`en`, `fr`), or its full
     * locale (`en-GB`, `en-US`) where two sites share a language.
     *
     * @return array<string, string> site handle => code
     */
    public function hreflangCodes(): array
    {
        $languages = Site::all()->map(fn ($site) => strtolower((string) $site->lang()))->countBy();

        return Site::all()->mapWithKeys(fn ($site) => [$site->handle() => $languages[strtolower((string) $site->lang())] > 1
            ? str_replace('_', '-', Str::before((string) $site->locale(), '.'))
            : (string) $site->lang(),
        ])->all();
    }

    /**
     * The site whose version is `x-default`: the default site, another named
     * in `seo.hreflang.x_default`, or none (false).
     */
    public function xDefaultSite(): ?string
    {
        $site = config('seo.hreflang.x_default');

        if ($site === false) {
            return null;
        }

        return is_string($site) && Site::get($site) ? $site : Site::default()->handle();
    }

    /**
     * og:locale:alternate: the locales of the page's other languages.
     *
     * @return list<string>
     */
    public function localeAlternates(Context $context): array
    {
        $alternates = $this->alternates($context);
        $own = $this->ogLocale($this->contentSite($context));

        return collect($this->hreflangCodes())
            ->filter(fn (string $code) => isset($alternates[$code]))
            ->keys()
            ->map(fn (string $site) => $this->ogLocale(Site::get($site)))
            ->reject(fn (string $locale) => $locale === $own)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The site of the page's content (its language), else the current one.
     */
    public function contentSite(Context $context): SiteObject
    {
        return $context->content()?->site() ?? Site::current();
    }

    protected function ogLocale(SiteObject $site): string
    {
        return str_replace('-', '_', Str::before((string) $site->locale(), '.'));
    }
}
