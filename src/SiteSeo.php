<?php

namespace JothamLec\MarketingToolkit;

use ArrayAccess;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JothamLec\MarketingToolkit\Support\SchemaTypes;
use JothamLec\MarketingToolkit\Support\Sites;
use JothamLec\MarketingToolkit\Support\Text;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Query\Builder;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Asset as Assets;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Image;
use Statamic\Facades\Markdown;
use Statamic\Facades\Site;
use Statamic\Fields\Value;
use Statamic\Sites\Site as SiteObject;
use Statamic\Structures\Page;
use WeakMap;

/**
 * The rules. Each public method works out one value for one page; a project
 * extends this class (config `seo.class`) and overrides only the rules it
 * needs to change. Every method receives the Context, so a rule can look at
 * the entry, the term, the request and the template's overrides.
 */
class SiteSeo
{
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
        $this->settings = new Settings;
    }

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

    /*
    |--------------------------------------------------------------------------
    | Title, description, image
    |--------------------------------------------------------------------------
    */

    public function title(Context $context): string
    {
        $site = $this->settings->siteName();
        $suffix = $context->page() > 1
            ? $this->settings->separator().__('seo::frontend.page', ['n' => $context->page()], $this->contentSite($context)->lang())
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

        $full = $title.$this->settings->separator().$site;

        return (mb_strlen($full.$suffix) <= (int) config('seo.title.max', 60) ? $full : $title).$suffix;
    }

    public function ogTitle(Context $context): string
    {
        return $context->override('title') ?? $context->seo()['title'] ?? $this->contentTitle($context) ?? $this->settings->siteName();
    }

    public function description(Context $context): ?string
    {
        // The meta tags and the JSON-LD nodes each ask; the body is read once per page.
        return $this->once($context, 'description', function () use ($context) {
            $text = $context->override('description')
                ?? $context->seo()['description']
                ?? $this->contentDescription($context)
                ?? $this->settings->string('default_description');

            return $text === null ? null : Text::limit(Text::plain($text), (int) config('seo.description.length', 155));
        });
    }

    /**
     * The share image: an override, the entry's uploaded one, an image field
     * the collection names, the generated card, then the site default.
     *
     * @return array{url: string, width: int, height: int, alt: ?string}|null
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

        // On the entry's own site's domain, which serves its card.
        return rtrim((string) $entry->site()->absoluteUrl(), '/').'/'.ltrim($route, '/').'?v='.$entry->lastModified()->timestamp;
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

        // Not following links leaves the snippet and image previews as they were.
        return implode(', ', array_filter([$nofollow ? 'nofollow' : null, $this->snippetRules($context)]));
    }

    /**
     * The default rules, with the page's own snippet limit: `nosnippet` keeps
     * its text out of results and of Google's AI Overviews and AI Mode; a
     * maximum length caps what is quoted.
     */
    protected function snippetRules(Context $context): string
    {
        $seo = $context->seo();
        $rules = (string) config('seo.robots.default');

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
            $this->productNode($context),
            $this->faqNode($context),
            ...$this->customNodes($context),
            ...$this->extraNodes($context),
        ]));
    }

    /**
     * The site, with its alternate name (Google's site names) when set.
     *
     * @return array<string, mixed>
     */
    public function websiteNode(): array
    {
        return array_filter([
            '@type' => 'WebSite',
            '@id' => $this->home().'#website',
            'url' => $this->home(),
            'name' => $this->settings->siteName(),
            'alternateName' => $this->settings->string('site_alternate_name'),
            'publisher' => ['@id' => $this->publisherId()],
        ]);
    }

    /**
     * Who is behind the site, from the global set: one or more schema.org
     * types (an Organization by default; a Person; a Store, an
     * EducationalOrganization…), with only the properties those types accept.
     *
     * @return array<string, mixed>
     */
    public function publisherNode(): array
    {
        $types = $this->publisherTypes();
        $person = SchemaTypes::isPerson($types);
        $organization = SchemaTypes::isOrganization($types);
        $local = SchemaTypes::isLocalBusiness($types);
        $logo = $this->settings->asset('publisher_logo');
        $logoUrl = $logo ? $this->absolute((string) $logo->url()) : null;

        return array_filter([
            '@type' => count($types) === 1 ? $types[0] : $types,
            '@id' => $this->publisherId(),
            'name' => $this->settings->string('publisher_name') ?? $this->settings->siteName(),
            'alternateName' => $this->settings->string('publisher_alternate_name'),
            'description' => $this->settings->string('publisher_description'),
            'url' => $this->home(),
            'logo' => $organization ? $logoUrl : null,
            'image' => $person || $local ? $logoUrl : null,
            'jobTitle' => $person ? $this->settings->string('job_title') : null,
            'telephone' => $this->settings->string('telephone'),
            'email' => $this->settings->string('email'),
            'address' => $this->postalAddress(),
            'areaServed' => $organization ? $this->settings->string('area_served') : null,
            'foundingDate' => $organization ? $this->settings->string('founding_date') : null,
            'contactPoint' => $organization ? $this->contactPoints() : null,
            'priceRange' => $local ? $this->settings->string('price_range') : null,
            'geo' => $local ? $this->geo() : null,
            'openingHoursSpecification' => $local ? $this->openingHours() : null,
            'sameAs' => $this->settings->list('same_as') ?: null,
            'hasMerchantReturnPolicy' => $organization ? $this->returnPolicy() : null,
            'hasShippingService' => $organization ? $this->shippingService() : null,
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * The shop's return policy, for all its products (Google's preference):
     * a window in days for a country, or just a link to the policy page.
     *
     * @return array<string, mixed>|null
     */
    protected function returnPolicy(): ?array
    {
        $link = $this->settings->string('return_policy_link');
        $country = $this->settings->string('return_country');
        $category = $this->settings->string('return_category');
        $days = $this->settings->string('return_days');

        if (! $link && ! ($country && $category)) {
            return null;
        }

        return array_filter([
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => $country,
            'returnPolicyCategory' => $category ? 'https://schema.org/'.$category : null,
            'merchantReturnDays' => $category === 'MerchantReturnFiniteReturnWindow' && is_numeric($days) ? (int) $days : null,
            'merchantReturnLink' => $link,
        ]);
    }

    /**
     * The shop's shipping rates, from a grid: where to, for orders of what
     * value, at what cost and how many days on the way.
     *
     * @return array<string, mixed>|null
     */
    protected function shippingService(): ?array
    {
        $currency = $this->settings->string('currency');

        $conditions = collect($this->settings->rows('shipping_rates'))
            ->filter(fn (array $row) => filled($row['country'] ?? null) && is_numeric($row['rate'] ?? null) && $currency)
            ->map(fn (array $row) => array_filter([
                '@type' => 'ShippingConditions',
                'shippingDestination' => array_filter(['@type' => 'DefinedRegion', 'addressCountry' => $row['country'], 'addressRegion' => $row['region'] ?? null]),
                'orderValue' => is_numeric($row['min_order'] ?? null) || is_numeric($row['max_order'] ?? null) ? array_filter([
                    '@type' => 'MonetaryAmount',
                    'minValue' => is_numeric($row['min_order'] ?? null) ? (float) $row['min_order'] : null,
                    'maxValue' => is_numeric($row['max_order'] ?? null) ? (float) $row['max_order'] : null,
                    'currency' => $currency,
                ], fn ($value) => $value !== null) : null,
                'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => (float) $row['rate'], 'currency' => $currency],
                'transitTime' => is_numeric($row['min_days'] ?? null) && is_numeric($row['max_days'] ?? null) ? [
                    '@type' => 'ServicePeriod',
                    'duration' => ['@type' => 'QuantitativeValue', 'minValue' => (int) $row['min_days'], 'maxValue' => (int) $row['max_days'], 'unitCode' => 'DAY'],
                ] : null,
            ]))
            ->values()
            ->all();

        return $conditions === [] ? null : ['@type' => 'ShippingService', 'shippingConditions' => $conditions];
    }

    /**
     * The publisher's schema.org types: a multiple select that also takes
     * types typed in, or a single type saved before it allowed several.
     *
     * @return list<string>
     */
    public function publisherTypes(): array
    {
        $types = $this->settings->list('publisher_type') ?: array_filter([$this->settings->string('publisher_type')]);

        return $types ?: ['Organization'];
    }

    /**
     * @return array<string, string>|null
     */
    protected function postalAddress(): ?array
    {
        $address = array_filter([
            'streetAddress' => $this->settings->string('street_address'),
            'addressLocality' => $this->settings->string('address_locality'),
            'addressRegion' => $this->settings->string('address_region'),
            'postalCode' => $this->settings->string('postal_code'),
            'addressCountry' => $this->settings->string('address_country'),
        ]);

        return $address === [] ? null : ['@type' => 'PostalAddress', ...$address];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function geo(): ?array
    {
        $latitude = $this->settings->string('latitude');
        $longitude = $this->settings->string('longitude');

        return is_numeric($latitude) && is_numeric($longitude)
            ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $latitude, 'longitude' => (float) $longitude]
            : null;
    }

    /**
     * Opening hours from a grid of days, opening and closing times.
     *
     * @return list<array<string, mixed>>
     */
    protected function openingHours(): array
    {
        return collect($this->settings->rows('opening_hours'))
            ->filter(fn (array $row) => filled($row['days'] ?? null) && filled($row['opens'] ?? null) && filled($row['closes'] ?? null))
            ->map(fn (array $row) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => array_values((array) $row['days']),
                'opens' => (string) $row['opens'],
                'closes' => (string) $row['closes'],
            ])
            ->values()
            ->all();
    }

    /**
     * Contact points (customer service, sales…) from a grid.
     *
     * @return list<array<string, mixed>>
     */
    protected function contactPoints(): array
    {
        return collect($this->settings->rows('contact_points'))
            ->filter(fn (array $row) => filled($row['telephone'] ?? null) || filled($row['email'] ?? null))
            ->map(fn (array $row) => array_filter([
                '@type' => 'ContactPoint',
                'contactType' => $row['contact_type'] ?? null,
                'telephone' => $row['telephone'] ?? null,
                'email' => $row['email'] ?? null,
            ]))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function webPageNode(Context $context): ?array
    {
        if (! $context->content()) {
            return null;
        }

        $type = $context->isHome() ? 'WebPage' : $this->contentConfig($context, 'page_schema', 'WebPage');
        $image = $this->image($context);

        return array_filter([
            '@type' => $type,
            '@id' => $this->url($context).'#webpage',
            'url' => $this->url($context),
            'name' => $this->ogTitle($context),
            'description' => $this->description($context),
            'isPartOf' => ['@id' => $this->home().'#website'],
            'inLanguage' => $this->contentSite($context)->lang(),
            // Where Google takes a page's thumbnail for Search and Discover from.
            'primaryImageOfPage' => $image ? ['@type' => 'ImageObject', 'url' => $image['url'], 'width' => $image['width'], 'height' => $image['height']] : null,
            // A profile page is about someone (Google requires it): the entry, as a Person.
            'mainEntity' => $type === 'ProfilePage' ? $this->profileEntity($context) : null,
        ]);
    }

    /**
     * Who a ProfilePage is about: a Person named by the entry, with its
     * address and picture. Override for an Organization, or to point at the
     * publisher.
     *
     * @return array<string, mixed>
     */
    public function profileEntity(Context $context): array
    {
        return array_filter([
            '@type' => 'Person',
            '@id' => $this->url($context).'#person',
            'name' => $this->contentTitle($context),
            'url' => $context->content()?->absoluteUrl(),
            'image' => $this->image($context)['url'] ?? null,
        ]);
    }

    /**
     * Home, then each published ancestor that is a page of its own, then this page.
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

            $ancestor = Entries::findByUri($path, Site::current()->handle());
            $ancestor = $ancestor instanceof Page ? $ancestor->entry() : $ancestor;

            // A draft's title and address aren't public yet.
            if ($ancestor instanceof Entry && $ancestor->status() === 'published') {
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
            'image' => $this->articleImages($context) ?: null,
            'datePublished' => $entry->hasDate() ? $entry->date()->toAtomString() : null,
            'dateModified' => $entry->lastModified()?->toAtomString(),
            'author' => $this->authors($context) ?: ['@id' => $this->publisherId()],
            'publisher' => ['@id' => $this->publisherId()],
            'mainEntityOfPage' => ['@id' => $this->canonical($context) ?? $this->url($context)],
        ]);
    }

    /**
     * An article's images: an uploaded one in the three shapes Google asks for
     * (16:9, 4:3, 1:1), else the share image.
     *
     * @return list<string>
     */
    public function articleImages(Context $context): array
    {
        if ($asset = $this->shareAsset($context)) {
            $width = $this->imageWidth();

            return [
                $this->cropped($asset, $width, (int) round($width * 9 / 16))['url'],
                $this->cropped($asset, $width, (int) round($width * 3 / 4))['url'],
                $this->cropped($asset, $width, $width)['url'],
            ];
        }

        return array_filter([$this->image($context)['url'] ?? null]);
    }

    /**
     * The people who wrote an article, from the field the collection names
     * (`author_field`): entries (a team collection) or users. Empty when there
     * is none, and the publisher stands as the author.
     *
     * @return list<array<string, mixed>>
     */
    public function authors(Context $context): array
    {
        $field = $this->collectionConfig($context, 'author_field');
        $value = $field ? $context->entry?->augmentedValue($field)->value() : null;
        $value = $value instanceof Builder ? $value->get() : $value;
        $items = is_iterable($value) ? collect($value) : collect(array_filter([$value]));

        return $items
            ->map(fn ($author) => match (true) {
                $author instanceof Entry => ['@type' => 'Person', 'name' => (string) $author->get('title'), 'url' => $author->absoluteUrl()],
                $author instanceof User => ['@type' => 'Person', 'name' => (string) ($author->name() ?: $author->get('name')), 'url' => $author->get('url')],
                default => null,
            })
            ->filter(fn ($author) => $author && filled($author['name']))
            ->map(fn (array $author) => array_filter($author))
            ->values()
            ->all();
    }

    /**
     * A product, from the fields the collection names (config `product`):
     * price, availability, SKU, GTIN and brand, with the shop's currency.
     * Left out without a price above zero, which Google requires.
     *
     * @return array<string, mixed>|null
     */
    public function productNode(Context $context): ?array
    {
        $fields = $this->collectionConfig($context, 'product');
        $entry = $context->entry;

        if (! is_array($fields) || ! $entry) {
            return null;
        }

        $value = fn (?string $field) => $field ? $this->plainValue($entry->augmentedValue($field)->value()) : null;
        $price = $value($fields['price_field'] ?? 'price');
        $currency = $fields['currency'] ?? $this->settings->string('currency');

        if (! is_numeric($price) || (float) $price <= 0 || ! $currency) {
            return null;
        }

        $brand = $value($fields['brand_field'] ?? null) ?? ($fields['brand'] ?? null);

        return array_filter([
            '@type' => 'Product',
            '@id' => $this->url($context).'#product',
            'name' => $this->contentTitle($context),
            'description' => $this->description($context),
            'image' => $this->articleImages($context) ?: null,
            'sku' => $value($fields['sku_field'] ?? null),
            'gtin' => $value($fields['gtin_field'] ?? null),
            'brand' => is_string($brand) && $brand !== '' ? ['@type' => 'Brand', 'name' => $brand] : null,
            'offers' => array_filter([
                '@type' => 'Offer',
                'url' => $this->url($context),
                'price' => (float) $price,
                'priceCurrency' => $currency,
                'availability' => $this->availability($value($fields['availability_field'] ?? null) ?? true),
                'itemCondition' => 'https://schema.org/'.($fields['condition'] ?? 'NewCondition'),
            ]),
        ], fn ($node) => $node !== null);
    }

    /**
     * schema.org availability from a toggle (in stock or not) or a value such
     * as `InStock`, `PreOrder` or `https://schema.org/OutOfStock`.
     */
    protected function availability(mixed $value): string
    {
        if (is_bool($value)) {
            return 'https://schema.org/'.($value ? 'InStock' : 'OutOfStock');
        }

        $value = (string) $value;

        return str_starts_with($value, 'http') ? $value : 'https://schema.org/'.Str::studly($value);
    }

    /**
     * A field's augmented value as a plain scalar: a select's value, a text.
     */
    protected function plainValue(mixed $value): mixed
    {
        $value = $value instanceof Value ? $value->value() : $value;

        if (is_object($value) && method_exists($value, 'value')) {
            $value = $value->value();
        }

        return $value === '' ? null : $value;
    }

    /**
     * FAQPage from a grid of question / answer rows (config `faq_field`), or
     * from that grid in each visible set of a Replicator (`sections.faq.faqs`),
     * in the page's order. Answers are rendered from Markdown, as the page
     * shows them.
     *
     * @return array<string, mixed>|null
     */
    public function faqNode(Context $context): ?array
    {
        $field = $this->contentConfig($context, 'faq_field');
        $content = $context->content();
        $rows = $field && $content ? $this->rawValues($content, $field)->flatMap(fn ($rows) => is_array($rows) ? $rows : []) : collect();

        $questions = $rows
            ->filter(fn ($row) => is_array($row) && filled($row['question'] ?? null) && filled($row['answer'] ?? null))
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
     * Project-specific nodes (Event, Course…). Empty by default.
     *
     * @return list<array<string, mixed>>
     */
    public function extraNodes(Context $context): array
    {
        return [];
    }

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

    /*
    |--------------------------------------------------------------------------
    | Sitemap
    |--------------------------------------------------------------------------
    */

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

        // Only canonical addresses: a page that names another (here or on another site) as its canonical is left out.
        return $content->url() !== null
            && ! ($content instanceof Entry && $content->isRedirect())
            && ! ($seo['noindex'] ?? false)
            && ($seo['sitemap'] ?? true) !== false
            && (blank($canonical) || rtrim((string) $canonical, '/') === rtrim((string) $content->absoluteUrl(), '/'));
    }

    /**
     * The sites one sitemap lists: the current one, and the others on its
     * domain (languages under /fr/, /de/). A domain serves one sitemap. In
     * Free, the default site only.
     *
     * @return list<string>
     */
    public function sitemapSites(): array
    {
        $host = fn ($site) => strtolower((string) parse_url((string) $site->absoluteUrl(), PHP_URL_HOST));
        $current = $host(Site::current());

        // Free lists the default site alone (several sites are Pro).
        $sites = Sites::multiple() ? Site::all() : collect([Site::default()]);

        return $sites->filter(fn ($site) => $host($site) === $current)->map->handle()->values()->all();
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: ?string, alternates?: array<string, string>}>
     */
    protected function sitemapEntries(): Collection
    {
        $sites = $this->sitemapSites();

        $collections = config('seo.sitemap.collections')
            ?? \Statamic\Facades\Collection::all()
                ->filter(fn ($collection) => collect($sites)->contains(fn (string $site) => $collection->route($site)))
                ->map->handle()->all();

        $collections = array_values(array_diff($collections, (array) config('seo.sitemap.exclude_collections')));

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
     * Whether a term has published entries, so its page is worth listing and
     * checking. Statamic counts only entries of the collections the taxonomy
     * is attached to; override for one that isn't attached (the entries name
     * their terms in a `terms` field), or to count only some entries. Only
     * the current site's entries count (Statamic counts every site's): the
     * sitemap and a report each ask on the site they are of.
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
        return collect($this->sitemapSites())->flatMap(fn (string $site) => Sites::as($site, fn () => collect((array) config('seo.sitemap.taxonomies'))
            ->flatMap(fn (string $taxonomy) => \Statamic\Facades\Term::query()->where('taxonomy', $taxonomy)->where('site', $site)->get())
            ->map(fn (Term $term) => $term->in($site))
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
        $handles = config('seo.sitemap.collections');
        $excluded = (array) config('seo.sitemap.exclude_collections');

        return \Statamic\Facades\Collection::all()
            ->filter(fn ($collection) => $collection->route(Site::current()->handle()) && ($handles === null || in_array($collection->handle(), (array) $handles, true)) && ! in_array($collection->handle(), $excluded, true))
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
        if (config('seo.robots.noindex_outside_production') && ! app()->isProduction()) {
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
     * A key of this page's rules: its collection's (config `seo.collections`),
     * or for a term, its taxonomy's (`seo.taxonomies`).
     */
    public function contentConfig(Context $context, string $key, mixed $default = null): mixed
    {
        if ($context->entry) {
            return $this->collectionConfig($context, $key, $default);
        }

        $handle = $context->term?->taxonomyHandle();

        return $handle ? config("seo.taxonomies.{$handle}.{$key}", $default) : $default;
    }

    /**
     * A key of this entry's collection settings (config `seo.collections`).
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

        foreach (['description', ...$this->contentConfig($context, 'description_fields', [])] as $field) {
            if ($value = $this->rawValues($content, $field)->first(fn ($value) => filled($value) && is_string($value))) {
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

        return Text::firstParagraph($html);
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
        if ($set = $this->setPath($field)) {
            return $this->visibleSets($content->augmentedValue($set['field'])->value(), $set['type'])
                ->map(fn ($values) => $this->asAsset($values[$set['key']] ?? null))
                ->first(fn ($asset) => $asset !== null);
        }

        return $this->asAsset($field === 'seo'
            ? ($content->augmentedValue('seo')->value()['image'] ?? null)
            : $content->augmentedValue($field)->value());
    }

    /**
     * The stored value of a field, as a list: one value for a plain field, one
     * per visible matching set for a path into a Replicator.
     *
     * @return Collection<int, mixed>
     */
    protected function rawValues(Entry|Term $content, string $field): Collection
    {
        if ($set = $this->setPath($field)) {
            return $this->visibleSets($content->get($set['field']), $set['type'])->map(fn ($values) => $values[$set['key']] ?? null)->values();
        }

        return collect([$content->get($field)]);
    }

    /**
     * `sections.hero.image` → the Replicator field, the set type (`*` for any)
     * and the field in the set. Statamic handles have no dots, so a plain name
     * is never a path.
     *
     * @return array{field: string, type: string, key: string}|null
     */
    protected function setPath(string $field): ?array
    {
        $parts = explode('.', $field);

        return count($parts) === 3 && ! in_array('', $parts, true) ? array_combine(['field', 'type', 'key'], $parts) : null;
    }

    /**
     * A Replicator's sets of a type, in order, without those switched off.
     * Takes the stored rows or the augmented ones (which have none switched off).
     *
     * @return Collection<int, mixed>
     */
    protected function visibleSets(mixed $sets, string $type): Collection
    {
        $sets = $sets instanceof Value ? $sets->value() : $sets;

        return collect(is_iterable($sets) ? $sets : [])
            ->filter(fn ($set) => (is_array($set) || $set instanceof ArrayAccess)
                && ($set['enabled'] ?? true) !== false
                && ($type === '*' || ($set['type'] ?? null) === $type))
            ->values();
    }

    private function asAsset(mixed $value): ?Asset
    {
        $value = $value instanceof Value ? $value->value() : $value;
        // A field that takes more than one file augments to a query, not a list.
        $value = $value instanceof Builder ? $value->get()->first() : $value;
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
    protected function cropped(Asset $asset, ?int $width = null, ?int $height = null): array
    {
        $width ??= $this->imageWidth();
        $height ??= $this->imageHeight();

        // Fluently, not as an array: only fit() turns `crop_focal` into Glide's
        // `crop-{x}-{y}`. Glide takes an unknown fit as `contain`, which neither
        // fills the card nor enlarges a small image.
        $url = Image::manipulate($asset)
            ->width($width)
            ->height($height)
            ->fit('crop_focal')
            ->format('jpg')
            ->quality(85)
            ->build();

        $alt = $asset->get('alt');

        return [
            'url' => $this->absolute((string) $url),
            'width' => $width,
            'height' => $height,
            'alt' => filled($alt) ? (string) $alt : null,
        ];
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

    /**
     * The share image's size, 1200×630 as Facebook, LinkedIn and X expect.
     * Override both for another.
     */
    protected function imageWidth(): int
    {
        return 1200;
    }

    protected function imageHeight(): int
    {
        return 630;
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
