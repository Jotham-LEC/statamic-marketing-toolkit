<?php

namespace JothamLec\MarketingToolkit\Concerns;

use Illuminate\Support\Str;
use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\SchemaTypes;
use JothamLec\MarketingToolkit\Support\Text;
use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Query\Builder;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Markdown;
use Statamic\Structures\Page;

/**
 * The JSON-LD @graph and each node in it. Part of SiteSeo's override
 * surface: a project overrides these methods on its SiteSeo subclass (bound in its place), not on the trait, which only splits the class into readable
 * parts.
 *
 * @phpstan-require-extends SiteSeo
 */
trait BuildsSchema
{
    /*
    |--------------------------------------------------------------------------
    | JSON-LD
    |--------------------------------------------------------------------------
    */

    /**
     * One @graph per page. Nodes point at each other by @id.
     *
     * @return list<array<string, mixed>>
     *
     * @api
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
     *
     * @api
     */
    public function websiteNode(): array
    {
        return array_filter([
            '@type' => 'WebSite',
            '@id' => $this->websiteId(),
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
     *
     * @api
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
     *
     * @api
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
     *
     * @api
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
            'isPartOf' => ['@id' => $this->websiteId()],
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
     *
     * @api
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
     *
     * @api
     */
    public function breadcrumbNode(Context $context): ?array
    {
        $content = $context->content();

        if (! $content || $context->isHome() || ! $content->url()) {
            return null;
        }

        $trail = collect([['name' => $this->settings->siteName(), 'item' => $this->home()]]);
        // The path on the content's site, without the site's folder (/fr/) a URL has.
        $segments = array_values(array_filter(explode('/', (string) $content->uri())));
        $site = $this->contentSite($context)->handle();
        $path = '';

        foreach (array_slice($segments, 0, -1) as $segment) {
            $path .= '/'.$segment;

            $ancestor = Entries::findByUri($path, $site);
            $ancestor = $ancestor instanceof Page ? $ancestor->entry() : $ancestor;

            // A draft's title and address aren't public yet.
            if ($ancestor instanceof Entry && $ancestor->status() === 'published') {
                $trail->push(['name' => (string) $ancestor->value('title'), 'item' => $ancestor->absoluteUrl()]);
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
     *
     * @api
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
     *
     * @api
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
     *
     * @api
     */
    public function authors(Context $context): array
    {
        $field = $this->collectionConfig($context, 'author_field');
        $value = $field ? $context->entry?->augmentedValue($field)->value() : null;
        $value = $value instanceof Builder ? $value->get()->all() : $value;
        $items = is_iterable($value) ? collect($value) : collect(array_filter([$value]));

        return $items
            ->map(fn ($author): ?array => match (true) {
                $author instanceof Entry => ['@type' => 'Person', 'name' => (string) $author->value('title'), 'url' => (string) $author->absoluteUrl()],
                $author instanceof User => ['@type' => 'Person', 'name' => (string) ($author->name() ?: $author->get('name')), 'url' => (string) $author->get('url')],
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
     *
     * @api
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
        $value = $this->unwrap($value);

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
     *
     * @api
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
     * Editors' nodes: the page's "Extra JSON-LD" field (SEO tab), an object or
     * a list of objects. Anything that does not parse is ignored rather than
     * breaking the page. Developers add theirs in extraNodes().
     *
     * @return list<array<string, mixed>>
     *
     * @api
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
     * Developers' nodes: override to add the site's own types (Event,
     * Course…) worked out in code. Empty by default; editors' hand-written
     * JSON-LD comes from customNodes().
     *
     * @return list<array<string, mixed>>
     *
     * @api
     */
    public function extraNodes(Context $context): array
    {
        return [];
    }
}
