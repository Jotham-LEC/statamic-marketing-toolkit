<?php

namespace JothamLec\MarketingToolkit;

use Closure;
use Illuminate\Support\Collection;
use JothamLec\MarketingToolkit\Concerns\BuildsMeta;
use JothamLec\MarketingToolkit\Concerns\BuildsSchema;
use JothamLec\MarketingToolkit\Concerns\InteractsWithContent;
use JothamLec\MarketingToolkit\Concerns\ResolvesAlternates;
use JothamLec\MarketingToolkit\Sitemap\Sitemap;
use JothamLec\MarketingToolkit\TextFiles\TextFiles;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Site;
use WeakMap;

/**
 * The rules. Each public method works out one value for one page; a project
 * extends this class, binds its subclass in its place in a service provider
 * (`$this->app->bind(SiteSeo::class, Seo::class)`), and overrides only the
 * rules it needs to change. Every method receives the Context, so a rule can
 * look at the entry, the term, the request and the template's overrides.
 * The traits only split the class into parts: override their methods here,
 * on the subclass. The sitemap and the text files are built by
 * Sitemap\Sitemap and TextFiles\TextFiles, which ask the hooks below.
 *
 * The methods marked `@api` (those docs/developers.md lists) keep their
 * names and signatures until the next major version; the others are
 * internal and may change in any release.
 */
class SiteSeo
{
    use BuildsMeta;
    use BuildsSchema;
    use InteractsWithContent;
    use ResolvesAlternates;

    /**
     * Crawlers and robots.txt tokens for AI training: OpenAI, Anthropic,
     * Google (Gemini; Search is unaffected), Apple, and Common Crawl, whose
     * open dataset AI developers train on.
     */
    public const array AI_TRAINING_AGENTS = ['GPTBot', 'ClaudeBot', 'Google-Extended', 'Applebot-Extended', 'CCBot'];

    /** Crawlers that index pages for AI search answers (ChatGPT, Claude, Perplexity). */
    public const array AI_SEARCH_AGENTS = ['OAI-SearchBot', 'Claude-SearchBot', 'PerplexityBot'];

    protected Settings $settings;

    /** @var WeakMap<Context, array<string, mixed>>|null values several rules ask for, per page */
    private ?WeakMap $worked = null;

    public function __construct()
    {
        // From the container, so a site or a test can bind its own.
        $this->settings = app(Settings::class);
    }

    /**
     * Every URL the sitemap lists, sorted by address, each with its other
     * languages (hreflang code => address) when it has any.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates?: array<string, string>}>
     */
    public function sitemapUrls(): Collection
    {
        return app(Sitemap::class)->urls($this);
    }

    /**
     * The sites one sitemap lists: the current one, and the others on its domain.
     *
     * @return list<string>
     */
    public function sitemapSites(): array
    {
        return app(Sitemap::class)->sites();
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

    /**
     * Whether a page is listed in the sitemap and llms.txt: it has an
     * address, isn't a redirect, noindexed, left out of the sitemap or
     * protected, and is its own canonical address.
     *
     * @api
     */
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

    /** @api */
    public function robotsTxt(): string
    {
        return app(TextFiles::class)->robots($this->hiddenOutsideProduction(), $this->absolute('/sitemap.xml'));
    }

    /**
     * /llms.txt (llmstxt.org): the site's name and description, then its
     * pages by collection, as Markdown links with their descriptions.
     *
     * @api
     */
    public function llmsTxt(): string
    {
        // A page's own SEO description, else one from its content (not the site's default, which every page would repeat).
        return app(TextFiles::class)->llms($this, $this->llmsPerCollection(), fn (Context $context) => $context->seo()['description'] ?? $this->contentDescription($context));
    }

    /** @api */
    protected function llmsPerCollection(): int
    {
        return 100;
    }

    /**
     * /ads.txt: the lines in Marketing settings → Crawlers, for a site that sells ad space.
     */
    public function adsTxt(): ?string
    {
        return app(TextFiles::class)->ads();
    }

    /**
     * $work's result for this page, worked out on the first ask. Keyed by the
     * Context itself (not Laravel's once(), which keys objects by an id PHP
     * reuses), so a page's values go when its Context does.
     */
    private function once(Context $context, string $key, Closure $work): mixed
    {
        $this->worked ??= new WeakMap;
        $values = $this->worked[$context] ?? [];

        if (! array_key_exists($key, $values)) {
            $values[$key] = $work();
            $this->worked[$context] = $values;
        }

        return $values[$key];
    }
}
