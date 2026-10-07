<?php

namespace JothamLec\MarketingToolkit\Concerns;

use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\Meta;
use JothamLec\MarketingToolkit\Og\Generator;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Text;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Entries\Entry;

/**
 * The page's meta tags: title, description, share image, canonical, robots
 * and the Open Graph extras.
 *
 * @phpstan-require-extends SiteSeo
 */
trait BuildsMeta
{
    /** @api */
    public function meta(Context $context): Meta
    {
        $canonical = $this->canonical($context);
        $title = $this->title($context);
        $alternates = $context->status >= 400 ? [] : $this->alternates($context);

        return new Meta(
            title: $title,
            description: $this->description($context),
            canonical: $canonical,
            robots: $this->robots($context),
            ogTitle: $this->ogTitle($context),
            ogType: $this->ogType($context),
            url: $this->url($context),
            siteName: $this->settings->siteName(),
            locale: $this->ogLocale($this->contentSite($context)),
            image: $this->image($context),
            published: $this->published($context),
            modified: $this->modified($context),
            twitterSite: $this->twitterSite(),
            verification: $this->verification(),
            graph: $context->status >= 400 ? [] : $this->graph($context),
            alternates: $alternates,
            localeAlternates: $alternates === [] ? [] : $this->localeAlternates($context),
        );
    }

    /** @api */
    public function title(Context $context): string
    {
        $site = $this->settings->siteName();
        $suffix = $context->page() > 1
            ? $this->settings->separator().__('marketing-toolkit::frontend.page', ['n' => $context->page()], $this->contentSite($context)->lang())
            : '';

        // The editor's SEO title is the whole <title>, as typed.
        if ($title = $context->seo()['title'] ?? null) {
            return $title.$suffix;
        }

        if ($context->isHome() && ! $context->override('title')) {
            return $site.$suffix;
        }

        // A template's title stands in for the content's own and gets the site name like it.
        $title = $context->override('title') ?? $this->contentTitle($context);

        if ($title === null) {
            return $site.$suffix;
        }

        if (! $this->settings->titleSiteName()) {
            return $title.$suffix;
        }

        $full = $title.$this->settings->separator().$site;

        return (mb_strlen($full.$suffix) <= (int) config('marketing-toolkit.title.max') ? $full : $title).$suffix;
    }

    /** @api */
    public function ogTitle(Context $context): string
    {
        return $context->override('title') ?? $context->seo()['title'] ?? $this->contentTitle($context) ?? $this->settings->siteName();
    }

    /** @api */
    public function description(Context $context): ?string
    {
        // The meta tags and the JSON-LD nodes each ask; the body is read once per page.
        return $this->once($context, 'description', function () use ($context) {
            $text = $context->override('description')
                ?? $context->seo()['description']
                ?? $this->contentDescription($context)
                ?? $this->settings->string('default_description');

            return $text === null ? null : Text::limit(Text::plain($text), (int) config('marketing-toolkit.description.length'));
        });
    }

    /**
     * The share image: an override, the entry's uploaded one, an image field
     * the collection names, the generated card, then the site default.
     *
     * @return array{url: string, width: int, height: int, alt: ?string}|null
     *
     * @api
     */
    public function image(Context $context): ?array
    {
        // Asked by the meta tags and the Article node: worked out once per page.
        return $this->once($context, 'image', function () use ($context) {
            if ($url = $context->override('image')) {
                return ['url' => $this->absolute($url), 'width' => $this->imageWidth(), 'height' => $this->imageHeight(), 'alt' => null];
            }

            if ($asset = $this->shareAsset($context)) {
                return $this->cropped($asset);
            }

            // The card shows the page's title, so that is what it says to someone who can't see it.
            if ($context->entry && $context->status < 400 && $url = $this->generatedImageUrl($context->entry)) {
                return ['url' => $url, 'width' => $this->imageWidth(), 'height' => $this->imageHeight(), 'alt' => $context->seo()['og_title'] ?? $this->contentTitle($context)];
            }

            if ($asset = $this->settings->asset('default_image')) {
                return $this->cropped($asset);
            }

            return null;
        });
    }

    /**
     * The page's own uploaded share image: its SEO image, else an image field
     * the collection names. A name with dots is a field in a Replicator's sets
     * (`sections.hero.image`, `*` for any set): the first visible set with one.
     */
    public function shareAsset(Context $context): ?Asset
    {
        $content = $context->content();

        foreach ($content ? ['seo', ...$this->contentConfig($context, 'image_fields', [])] : [] as $field) {
            if ($asset = $this->assetFrom($content, $field)) {
                return $asset;
            }
        }

        return null;
    }

    /**
     * The URL of the entry's generated card, or null when cards are off, this
     * host can't draw them (no Imagick), or the entry is protected (its card
     * would show what it protects). The
     * `v` parameter changes with each edit, so link previews refetch it.
     *
     * On the root of the entry's domain, which serves the card routes, with
     * the page's path from that root: /fr/a-propos's card is
     * /og/fr/a-propos.png, and the controller finds the site from the path
     * as Statamic finds a page's.
     */
    public function generatedImageUrl(Entry $entry): ?string
    {
        if (! Features::on('share_cards') || $entry->status() !== 'published' || ! $entry->url() || $this->isProtected($entry)
            || ! app(Generator::class)->available()) {
            return null;
        }

        $absolute = (string) $entry->absoluteUrl();
        $path = trim((string) parse_url($absolute, PHP_URL_PATH), '/');
        $route = $path === '' ? route('mt.og.home', [], false) : route('mt.og', ['path' => $path], false);

        return self::domainRoot($absolute).'/'.ltrim($route, '/').'?v='.$entry->lastModified()->timestamp;
    }

    /**
     * Where search engines should send the ranking: an override, the original
     * a republished piece points at, else this page (with `?page=N` past the
     * first page). `false` as an override prints no canonical at all.
     *
     * @api
     */
    public function canonical(Context $context): ?string
    {
        $override = $context->override('canonical');

        if ($override === false || $context->status >= 400) {
            return null;
        }

        if ($override || ($override = $context->seo()['canonical'] ?? null)) {
            return $override;
        }

        return $this->url($context);
    }

    /**
     * This page's own address (og:url): the entry's, else the request's,
     * never a query string other than `page`.
     */
    public function url(Context $context): string
    {
        $url = $context->content()?->absoluteUrl() ?? $context->request->url();

        return $context->page() > 1 ? $url.'?page='.$context->page() : $url;
    }

    /** @api */
    public function robots(Context $context): string
    {
        $seo = $context->seo();
        $noindex = $context->override('noindex') ?? (bool) ($seo['noindex'] ?? false);
        $nofollow = (bool) ($seo['nofollow'] ?? false);

        if ($noindex || $this->shouldNoindex($context)) {
            return implode(', ', ['noindex', $nofollow ? 'nofollow' : 'follow']);
        }

        // Not following links leaves the snippet and image previews as they were.
        return implode(', ', array_filter([$nofollow ? 'nofollow' : null, $this->snippetRules($context)]));
    }

    /**
     * The default rules, with the page's own snippet limit: `nosnippet` keeps
     * its text out of results and of Google's AI Overviews and AI Mode; a
     * maximum length caps what is quoted.
     *
     * @api
     */
    protected function snippetRules(Context $context): string
    {
        $seo = $context->seo();
        $rules = (string) config('marketing-toolkit.robots.default');

        if ($seo['nosnippet'] ?? false) {
            return trim((string) preg_replace('/max-snippet:-?\d+/', 'nosnippet', $rules)) ?: 'nosnippet';
        }

        if (isset($seo['max_snippet']) && is_numeric($seo['max_snippet']) && (int) $seo['max_snippet'] >= 0) {
            $limit = 'max-snippet:'.(int) $seo['max_snippet'];

            return str_contains($rules, 'max-snippet:') ? (string) preg_replace('/max-snippet:-?\d+/', $limit, $rules) : trim("{$limit}, {$rules}", ', ');
        }

        return $rules;
    }

    /**
     * Whether search engines are kept off this copy of the site: every page
     * noindexed and robots.txt disallowing all, unless APP_ENV is production
     * (config `marketing-toolkit.robots.noindex_outside_production`).
     *
     * @api
     */
    protected function hiddenOutsideProduction(): bool
    {
        return config('marketing-toolkit.robots.noindex_outside_production') && ! app()->isProduction();
    }

    /**
     * Site-wide reasons to keep a page out of the index. Override to add your
     * own (an empty taxonomy listing, a thank-you page).
     *
     * @api
     */
    public function shouldNoindex(Context $context): bool
    {
        if ($this->hiddenOutsideProduction()) {
            return true;
        }

        if ($context->status >= 400) {
            return true;
        }

        foreach ((array) config('marketing-toolkit.robots.noindex_params') as $param) {
            if (filled($context->request->query($param))) {
                return true;
            }
        }

        $route = $context->request->route()?->getName();

        return $route !== null && in_array($route, (array) config('marketing-toolkit.robots.noindex_routes'), true);
    }

    /** @api */
    public function ogType(Context $context): string
    {
        return $context->override('og_type')
            ?? ($context->isHome() ? 'website' : $this->contentConfig($context, 'og_type', 'website'));
    }

    public function published(Context $context): ?string
    {
        $entry = $context->entry;

        return $this->ogType($context) === 'article' && $entry?->hasDate() ? $entry->date()->toAtomString() : null;
    }

    public function modified(Context $context): ?string
    {
        return $this->ogType($context) === 'article' ? $context->entry?->lastModified()?->toAtomString() : null;
    }

    public function twitterSite(): ?string
    {
        $handle = $this->settings->string('twitter_handle');

        return $handle === null ? null : '@'.ltrim($handle, '@');
    }

    /**
     * @return array<string, string>
     */
    public function verification(): array
    {
        return array_filter([
            'google-site-verification' => $this->settings->string('google_verification'),
            'msvalidate.01' => $this->settings->string('bing_verification'),
            'yandex-verification' => $this->settings->string('yandex_verification'),
            'p:domain_verify' => $this->settings->string('pinterest_verification'),
        ]);
    }
}
