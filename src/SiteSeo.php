<?php

namespace JothamLec\MarketingToolkit;

use Closure;
use JothamLec\MarketingToolkit\Concerns\BuildsMeta;
use JothamLec\MarketingToolkit\Concerns\BuildsSchema;
use JothamLec\MarketingToolkit\Concerns\BuildsSitemap;
use JothamLec\MarketingToolkit\Concerns\BuildsTextFiles;
use JothamLec\MarketingToolkit\Concerns\InteractsWithContent;
use JothamLec\MarketingToolkit\Concerns\ResolvesAlternates;
use WeakMap;

/**
 * The rules. Each public method works out one value for one page; a project
 * extends this class, binds its subclass in its place in a service provider
 * (`$this->app->bind(SiteSeo::class, Seo::class)`), and overrides only the
 * rules it needs to change. Every method receives the Context, so a rule can
 * look at the entry, the term, the request and the template's overrides.
 * The traits only split the class into parts: override their methods here,
 * on the subclass.
 *
 * The methods marked `@api` (those docs/developers.md lists) keep their
 * names and signatures until the next major version; the others are
 * internal and may change in any release.
 */
class SiteSeo
{
    use BuildsMeta;
    use BuildsSchema;
    use BuildsSitemap;
    use BuildsTextFiles;
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
