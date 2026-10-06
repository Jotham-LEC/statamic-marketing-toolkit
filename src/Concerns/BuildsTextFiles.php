<?php

namespace JothamLec\MarketingToolkit\Concerns;

use Illuminate\Support\Collection;
use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Text;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Site;

/**
 * llms.txt, ads.txt and robots.txt. Part of SiteSeo's override surface: a
 * project overrides these methods on its SiteSeo subclass (config
 * `seo.class`), not on the trait, which only splits the class into readable
 * parts.
 *
 * @phpstan-require-extends SiteSeo
 */
trait BuildsTextFiles
{
    /*
    |--------------------------------------------------------------------------
    | llms.txt and ads.txt
    |--------------------------------------------------------------------------
    */

    /**
     * /llms.txt (llmstxt.org): the site's name and description, then, per
     * collection the sitemap lists, its pages as Markdown links with their
     * descriptions, the most recently changed first. For AI assistants that
     * read a site's summary before its pages.
     */
    public function llmsTxt(): string
    {
        $lines = ['# '.$this->settings->siteName(), ''];

        if ($description = $this->settings->string('default_description')) {
            $lines = [...$lines, '> '.Text::plain($description), ''];
        }

        foreach ($this->llmsCollections() as $collection) {
            $entries = Entries::query()
                ->where('collection', $collection->handle())
                ->where('site', Site::current()->handle())
                ->whereStatus('published')
                ->get()
                ->filter(fn (Entry $entry) => $this->inSitemap($entry))
                ->sortByDesc(fn (Entry $entry) => $entry->lastModified()?->timestamp)
                ->take($this->llmsPerCollection());

            if ($entries->isEmpty()) {
                continue;
            }

            $lines[] = '## '.$collection->title();
            $lines[] = '';

            foreach ($entries as $entry) {
                $context = Context::make($entry);
                $description = $context->seo()['description'] ?? $this->contentDescription($context);
                $description = $description === null ? null : Text::limit(Text::plain($description), 200);
                $title = str_replace(['[', ']'], ['(', ')'], (string) $entry->get('title'));

                $lines[] = '- ['.$title.']('.$entry->absoluteUrl().')'.($description ? ': '.$description : '');
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * The collections llms.txt lists: those the sitemap lists, by title.
     *
     * @return Collection<int, \Statamic\Contracts\Entries\Collection>
     */
    protected function llmsCollections(): Collection
    {
        return $this->sitemapCollections([Site::current()->handle()])
            ->sortBy(fn ($collection) => $collection->title())
            ->values();
    }

    protected function llmsPerCollection(): int
    {
        return 100;
    }

    /**
     * /ads.txt: the lines in SEO & brand → Crawlers, for a site that sells ad space.
     */
    public function adsTxt(): ?string
    {
        $text = trim((string) $this->settings->string('ads_txt'));

        return $text === '' ? null : $text."\n";
    }

    /*
    |--------------------------------------------------------------------------
    | Robots.txt
    |--------------------------------------------------------------------------
    */

    public function robotsTxt(): string
    {
        if ($this->hiddenOutsideProduction()) {
            return "User-agent: *\nDisallow: /\n";
        }

        $lines = ['User-agent: *'];

        $disallow = $this->settings->list('robots_disallow') ?: ['/'.trim((string) config('statamic.cp.route', 'cp'), '/').'/'];

        foreach ($disallow as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        foreach ($this->aiCrawlerRules() as $line) {
            $lines[] = $line;
        }

        if ($extra = $this->settings->string('robots_extra')) {
            $lines[] = '';
            $lines[] = trim($extra);
        }

        if (config('seo.sitemap.enabled')) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.$this->absolute(route('seo.sitemap', [], false));
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * robots.txt groups for AI crawlers the brand global turns away: those
     * that gather training data, and those that index for AI search answers.
     * Fetchers a person sends (ChatGPT-User, Perplexity-User) don't all read
     * robots.txt, so they aren't listed.
     *
     * @return list<string>
     */
    protected function aiCrawlerRules(): array
    {
        $agents = [
            ...($this->settings->bool('allow_ai_training', true) ? [] : self::AI_TRAINING_AGENTS),
            ...($this->settings->bool('allow_ai_search', true) ? [] : self::AI_SEARCH_AGENTS),
        ];

        if ($agents === []) {
            return [];
        }

        return ['', ...array_map(fn (string $agent) => 'User-agent: '.$agent, $agents), 'Disallow: /'];
    }
}
