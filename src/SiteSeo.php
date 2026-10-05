<?php

namespace JothamLec\Seo;

use Illuminate\Support\Collection;
use JothamLec\Seo\Support\Text;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Asset as Assets;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Image;
use Statamic\Facades\Markdown;
use Statamic\Facades\Site;
use Statamic\Fields\Value;

/**
 * The rules. Each public method works out one value for one page; a project
 * extends this class (config `seo.class`) and overrides only the rules it
 * needs to change. Every method receives the Context, so a rule can look at
 * the entry, the term, the request and the template's overrides.
 */
class SiteSeo
{
    protected Settings $settings;

    public function __construct()
    {
        $this->settings = new Settings;
    }

    public function meta(Context $context): Meta
    {
        $canonical = $this->canonical($context);
        $title = $this->title($context);

        return new Meta(
            title: $title,
            description: $this->description($context),
            canonical: $canonical,
            robots: $this->robots($context),
            ogTitle: $this->ogTitle($context),
            ogType: $this->ogType($context),
            url: $this->url($context),
            siteName: $this->settings->siteName(),
            locale: str_replace('-', '_', Site::current()->locale()),
            image: $this->image($context),
            published: $this->published($context),
            modified: $this->modified($context),
            twitterSite: $this->twitterSite(),
            verification: $this->verification(),
            graph: $context->status >= 400 ? [] : $this->graph($context),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Title, description, image
    |--------------------------------------------------------------------------
    */

    public function title(Context $context): string
    {
        $site = $this->settings->siteName();
        $suffix = $context->page() > 1 ? "{$this->settings->separator()}Page {$context->page()}" : '';

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

        $full = $title.$this->settings->separator().$site;

        return (mb_strlen($full.$suffix) <= (int) config('seo.title.max', 60) ? $full : $title).$suffix;
    }

    public function ogTitle(Context $context): string
    {
        return $context->override('title') ?? $context->seo()['title'] ?? $this->contentTitle($context) ?? $this->settings->siteName();
    }

    public function description(Context $context): ?string
    {
        $text = $context->override('description')
            ?? $context->seo()['description']
            ?? $this->contentDescription($context)
            ?? $this->settings->string('default_description');

        return $text === null ? null : Text::limit(Text::plain($text), (int) config('seo.description.length', 155));
    }

    /**
     * The share image: an override, the entry's uploaded one, an image field
     * the collection names, the generated card, then the site default.
     *
     * @return array{url: string, width: int, height: int, alt: ?string}|null
     */
    public function image(Context $context): ?array
    {
        if ($url = $context->override('image')) {
            return ['url' => $this->absolute($url), 'width' => $this->imageWidth(), 'height' => $this->imageHeight(), 'alt' => null];
        }

        $content = $context->content();

        foreach (['seo', ...$this->collectionConfig($context, 'image_fields', [])] as $field) {
            if ($content && $asset = $this->assetFrom($content, $field)) {
                return $this->cropped($asset);
            }
        }

        if ($context->entry && $context->status < 400 && $url = $this->generatedImageUrl($context->entry)) {
            return ['url' => $url, 'width' => $this->imageWidth(), 'height' => $this->imageHeight(), 'alt' => null];
        }

        if ($asset = $this->settings->asset('default_image')) {
            return $this->cropped($asset);
        }

        return null;
    }

    /**
     * The URL of the entry's generated card, or null when cards are off. The
     * `v` parameter changes with each edit, so link previews refetch it.
     */
    public function generatedImageUrl(Entry $entry): ?string
    {
        if (! config('seo.og.enabled') || $entry->status() !== 'published' || ! $entry->url()) {
            return null;
        }

        $path = trim((string) $entry->uri(), '/');
        $route = $path === '' ? route('seo.og.home', [], false) : route('seo.og', ['path' => $path], false);

        return $this->absolute($route.'?v='.$entry->lastModified()->timestamp);
    }

    /*
    |--------------------------------------------------------------------------
    | URLs and robots
    |--------------------------------------------------------------------------
    */

    /**
     * Where search engines should send the ranking: an override, the original
     * a republished piece points at, else this page (with `?page=N` past the
     * first page). `false` as an override prints no canonical at all.
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

    public function robots(Context $context): string
    {
        $seo = $context->seo();
        $noindex = $context->override('noindex') ?? (bool) ($seo['noindex'] ?? false);
        $nofollow = (bool) ($seo['nofollow'] ?? false);

        if ($noindex || $this->shouldNoindex($context)) {
            return implode(', ', ['noindex', $nofollow ? 'nofollow' : 'follow']);
        }

        return $nofollow ? 'nofollow' : (string) config('seo.robots.default');
    }

    /**
     * Site-wide reasons to keep a page out of the index. Override to add your
     * own (an empty taxonomy listing, a thank-you page).
     */
    public function shouldNoindex(Context $context): bool
    {
        if (config('seo.robots.noindex_outside_production') && ! app()->isProduction()) {
            return true;
        }

        if ($context->status >= 400) {
            return true;
        }

        foreach ((array) config('seo.robots.noindex_params') as $param) {
            if (filled($context->request->query($param))) {
                return true;
            }
        }

        $route = $context->request->route()?->getName();

        return $route !== null && in_array($route, (array) config('seo.robots.noindex_routes'), true);
    }

    /*
    |--------------------------------------------------------------------------
    | Open Graph extras
    |--------------------------------------------------------------------------
    */

    public function ogType(Context $context): string
    {
        return $context->override('og_type')
            ?? $context->seo()['og_type']
            ?? ($context->isHome() ? 'website' : $this->collectionConfig($context, 'og_type', 'website'));
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

    /*
    |--------------------------------------------------------------------------
    | JSON-LD
    |--------------------------------------------------------------------------
    */

    /**
     * One @graph per page. Nodes point at each other by @id.
     *
     * @return list<array<string, mixed>>
     */
    public function graph(Context $context): array
    {
        return array_values(array_filter([
            $this->websiteNode(),
            $this->publisherNode(),
            $this->webPageNode($context),
            $this->breadcrumbNode($context),
            $this->articleNode($context),
            $this->faqNode($context),
            ...$this->customNodes($context),
            ...$this->extraNodes($context),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function websiteNode(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => $this->home().'#website',
            'url' => $this->home(),
            'name' => $this->settings->siteName(),
            'publisher' => ['@id' => $this->publisherId()],
        ];
    }

    /**
     * The organisation, local business or person behind the site, from the
     * global set.
     *
     * @return array<string, mixed>
     */
    public function publisherNode(): array
    {
        $logo = $this->settings->asset('publisher_logo');
        $type = $this->settings->string('publisher_type', 'Organization');

        return array_filter([
            '@type' => $type,
            '@id' => $this->publisherId(),
            'name' => $this->settings->string('publisher_name') ?? $this->settings->siteName(),
            'url' => $this->home(),
            $type === 'Person' ? 'image' : 'logo' => $logo ? $this->absolute((string) $logo->url()) : null,
            'jobTitle' => $type === 'Person' ? $this->settings->string('job_title') : null,
            'telephone' => $this->settings->string('telephone'),
            'email' => $this->settings->string('email'),
            'areaServed' => $this->settings->string('area_served'),
            'priceRange' => $this->settings->string('price_range'),
            'sameAs' => $this->settings->list('same_as') ?: null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function webPageNode(Context $context): ?array
    {
        if (! $context->content()) {
            return null;
        }

        return array_filter([
            '@type' => $context->isHome() ? 'WebPage' : $this->collectionConfig($context, 'page_schema', 'WebPage'),
            '@id' => $this->url($context).'#webpage',
            'url' => $this->url($context),
            'name' => $this->ogTitle($context),
            'description' => $this->description($context),
            'isPartOf' => ['@id' => $this->home().'#website'],
            'inLanguage' => Site::current()->lang(),
        ]);
    }

    /**
     * Home, then each ancestor that is a page of its own, then this page.
     *
     * @return array<string, mixed>|null
     */
    public function breadcrumbNode(Context $context): ?array
    {
        $content = $context->content();

        if (! $content || $context->isHome() || ! $content->url()) {
            return null;
        }

        $trail = collect([['name' => $this->settings->siteName(), 'item' => $this->home()]]);
        $segments = array_values(array_filter(explode('/', (string) $content->url())));
        $path = '';

        foreach (array_slice($segments, 0, -1) as $segment) {
            $path .= '/'.$segment;

            if ($ancestor = Entries::findByUri($path, Site::current()->handle())) {
                $trail->push(['name' => (string) $ancestor->get('title'), 'item' => $ancestor->absoluteUrl()]);
            }
        }

        $trail->push(['name' => (string) $this->contentTitle($context), 'item' => $content->absoluteUrl()]);

        return [
            '@type' => 'BreadcrumbList',
            '@id' => $this->url($context).'#breadcrumb',
            'itemListElement' => $trail->values()->map(fn (array $crumb, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                ...$crumb,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function articleNode(Context $context): ?array
    {
        $type = $this->collectionConfig($context, 'schema');
        $entry = $context->entry;

        if (! $type || ! $entry) {
            return null;
        }

        return array_filter([
            '@type' => $type,
            '@id' => $this->url($context).'#article',
            'headline' => $this->contentTitle($context),
            'description' => $this->description($context),
            'image' => $this->image($context)['url'] ?? null,
            'datePublished' => $entry->hasDate() ? $entry->date()->toAtomString() : null,
            'dateModified' => $entry->lastModified()?->toAtomString(),
            'author' => ['@id' => $this->publisherId()],
            'publisher' => ['@id' => $this->publisherId()],
            'mainEntityOfPage' => ['@id' => $this->canonical($context) ?? $this->url($context)],
        ]);
    }

    /**
     * FAQPage from a grid of question / answer rows (config `faq_field`).
     * Answers are rendered from Markdown, as the page shows them.
     *
     * @return array<string, mixed>|null
     */
    public function faqNode(Context $context): ?array
    {
        $field = $this->collectionConfig($context, 'faq_field');
        $rows = $field ? $context->content()?->get($field) : null;

        $questions = collect(is_array($rows) ? $rows : [])
            ->filter(fn ($row) => filled($row['question'] ?? null) && filled($row['answer'] ?? null))
            ->map(fn (array $row) => [
                '@type' => 'Question',
                'name' => Text::plain($row['question']),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(Markdown::parse((string) $row['answer']))],
            ])
            ->values();

        return $questions->isEmpty() ? null : [
            '@type' => 'FAQPage',
            '@id' => $this->url($context).'#faq',
            'mainEntity' => $questions->all(),
        ];
    }

    /**
     * The entry's "Extra JSON-LD" field: an object or a list of objects.
     * Anything that does not parse is ignored rather than breaking the page.
     *
     * @return list<array<string, mixed>>
     */
    public function customNodes(Context $context): array
    {
        $json = $context->seo()['json_ld'] ?? null;
        $json = is_array($json) ? ($json['code'] ?? null) : $json;
        $decoded = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        return array_is_list($decoded) ? array_values(array_filter($decoded, 'is_array')) : [$decoded];
    }

    /**
     * Project-specific nodes (Product, Offer, Event…). Empty by default.
     *
     * @return list<array<string, mixed>>
     */
    public function extraNodes(Context $context): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | Sitemap
    |--------------------------------------------------------------------------
    */

    /**
     * Every URL the sitemap lists, sorted by address.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string}>
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
     */
    public function additionalSitemapUrls(): array
    {
        return [];
    }

    public function inSitemap(Entry|Term $content): bool
    {
        $seo = $content->get('seo');
        $seo = is_array($seo) ? $seo : [];
        $canonical = $seo['canonical'] ?? null;

        return $content->url() !== null
            && ! ($content instanceof Entry && $content->isRedirect())
            && ! ($seo['noindex'] ?? false)
            && ($seo['sitemap'] ?? true) !== false
            && (blank($canonical) || str_starts_with($canonical, $this->home()));
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: ?string}>
     */
    protected function sitemapEntries(): Collection
    {
        $collections = config('seo.sitemap.collections')
            ?? \Statamic\Facades\Collection::all()->filter(fn ($collection) => $collection->route(Site::current()->handle()))->map->handle()->all();

        $collections = array_values(array_diff($collections, (array) config('seo.sitemap.exclude_collections')));

        if ($collections === []) {
            return collect();
        }

        return Entries::query()
            ->whereIn('collection', $collections)
            ->where('site', Site::current()->handle())
            ->whereStatus('published')
            ->get()
            ->filter(fn (Entry $entry) => $this->inSitemap($entry))
            ->map(fn (Entry $entry) => ['loc' => $entry->absoluteUrl(), 'lastmod' => $entry->lastModified()?->toAtomString()])
            ->values();
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: ?string}>
     */
    protected function sitemapTerms(): Collection
    {
        return collect((array) config('seo.sitemap.taxonomies'))
            ->flatMap(fn (string $taxonomy) => \Statamic\Facades\Term::query()->where('taxonomy', $taxonomy)->get())
            ->filter(fn (Term $term) => $this->inSitemap($term) && $term->queryEntries()->whereStatus('published')->count() > 0)
            ->map(fn (Term $term) => ['loc' => $term->absoluteUrl(), 'lastmod' => $term->lastModified()?->toAtomString()])
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Robots.txt and humans.txt
    |--------------------------------------------------------------------------
    */

    public function robotsTxt(): string
    {
        $lines = ['User-agent: *'];

        if (config('seo.robots.noindex_outside_production') && ! app()->isProduction()) {
            return "User-agent: *\nDisallow: /\n";
        }

        $disallow = $this->settings->list('robots_disallow') ?: ['/'.trim((string) config('statamic.cp.route', 'cp'), '/').'/'];

        foreach ($disallow as $path) {
            $lines[] = 'Disallow: '.$path;
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

    public function humansTxt(): ?string
    {
        return $this->settings->string('humans');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function settings(): Settings
    {
        return $this->settings;
    }

    /**
     * A key of this page's collection settings (config `seo.collections`).
     */
    public function collectionConfig(Context $context, string $key, mixed $default = null): mixed
    {
        $handle = $context->entry?->collectionHandle();

        return $handle ? config("seo.collections.{$handle}.{$key}", $default) : $default;
    }

    protected function contentTitle(Context $context): ?string
    {
        $content = $context->content();
        $title = $content instanceof Term ? $content->title() : $content?->get('title');

        return filled($title) ? (string) $title : null;
    }

    /**
     * The entry's `description`, a field the collection names, else the first
     * paragraph of its `content`.
     */
    protected function contentDescription(Context $context): ?string
    {
        $content = $context->content();

        if (! $content) {
            return null;
        }

        foreach (['description', ...$this->collectionConfig($context, 'description_fields', [])] as $field) {
            if (filled($value = $content->get($field)) && is_string($value)) {
                return $value;
            }
        }

        $html = $this->html($content->augmentedValue('content'));

        if (blank($html)) {
            return null;
        }

        // A body that is not rendered HTML (no blueprint field, a plain textarea) is read as Markdown.
        if (! str_contains($html, '<p')) {
            $html = Markdown::parse($html);
        }

        return Text::firstParagraph($html, (array) config('seo.description.skip_prefixes'));
    }

    /**
     * HTML from an augmented Markdown or Bard value.
     */
    protected function html(mixed $value): ?string
    {
        $value = $value instanceof Value ? $value->value() : $value;

        if (is_string($value)) {
            return $value;
        }

        if (is_iterable($value)) {
            return collect($value)
                ->map(fn ($set) => ($set['type'] ?? null) === 'text' ? (string) ($set['text'] ?? '') : '')
                ->implode('');
        }

        return null;
    }

    protected function assetFrom(Entry|Term $content, string $field): ?Asset
    {
        $value = $field === 'seo'
            ? ($content->augmentedValue('seo')->value()['image'] ?? null)
            : $content->augmentedValue($field)->value();

        $value = $value instanceof Value ? $value->value() : $value;
        $value = is_iterable($value) && ! $value instanceof Asset ? collect($value)->first() : $value;

        // Without a blueprint field to augment through, a stored "container::path" id still resolves.
        if (is_string($value)) {
            $value = Assets::find($value);
        }

        return $value instanceof Asset ? $value : null;
    }

    /**
     * An uploaded image as a share image: cropped on its focal point and
     * served as JPEG (WhatsApp and others do not preview WebP).
     *
     * @return array{url: string, width: int, height: int, alt: ?string}
     */
    protected function cropped(Asset $asset): array
    {
        $url = Image::manipulate($asset, [
            'w' => $this->imageWidth(),
            'h' => $this->imageHeight(),
            'fit' => 'crop_focal',
            'fm' => 'jpg',
            'q' => 85,
        ]);

        $alt = $asset->get('alt');

        return [
            'url' => $this->absolute((string) $url),
            'width' => $this->imageWidth(),
            'height' => $this->imageHeight(),
            'alt' => filled($alt) ? (string) $alt : null,
        ];
    }

    protected function imageWidth(): int
    {
        return (int) config('seo.image.width', 1200);
    }

    protected function imageHeight(): int
    {
        return (int) config('seo.image.height', 630);
    }

    protected function publisherId(): string
    {
        return $this->home().'#publisher';
    }

    protected function home(): string
    {
        return rtrim(Site::current()->absoluteUrl(), '/').'/';
    }

    /**
     * A site-relative URL made absolute against the current site's address.
     */
    public function absolute(string $url): string
    {
        return preg_match('#^https?://#i', $url) ? $url : rtrim(Site::current()->absoluteUrl(), '/').'/'.ltrim($url, '/');
    }
}
