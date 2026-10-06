# For developers

How the addon works out each value, and where to change it for one site.

## How a value is worked out

Every value the `<head>` prints comes from one public method of `JothamLec\Seo\SiteSeo`, called with a `Context`:

```php
final readonly class Context
{
    public ?Entry $entry;     // the entry being shown, if any
    public ?Term $term;       // or the term
    public Request $request;
    public array $overrides;  // what the template passed: title, description, canonical, image, og_type, noindex
    public int $status;       // 200, or what the template passed (404…)

    public function content(): Entry|Term|null;
    public function seo(): array;      // the content's `seo` group, without empty fields
    public function override(string $key): mixed;
    public function isHome(): bool;
    public function page(): int;       // ?page=N, at least 1
}
```

`SiteSeo::meta($context)` collects everything into a readonly `Meta` object that `resources/views/meta.blade.php` prints. The same rules feed the sitemap, the share cards, the control panel preview and the reports, so a change in one place shows everywhere.

## Change a rule: SiteSeo

Extend the class, override the methods you need, and point `seo.class` at it:

```php
// app/Seo.php
namespace App;

use JothamLec\Seo\Context;
use JothamLec\Seo\SiteSeo;

class Seo extends SiteSeo
{
    public function extraNodes(Context $context): array
    {
        if ($context->entry?->collectionHandle() !== 'products') {
            return [];
        }

        return [[
            '@type' => 'Product',
            'name' => $context->entry->get('title'),
            'offers' => ['@type' => 'Offer', 'price' => $context->entry->get('price'), 'priceCurrency' => 'USD'],
        ]];
    }
}
```

```php
// config/seo.php
'class' => App\Seo::class,
```

Config can't hold closures (`config:cache` can't serialise them), which is why rules live in a class.

The methods you're most likely to override:

| Method | Returns |
|---|---|
| `title(Context)` | The `<title>`. |
| `ogTitle(Context)` | og:title and twitter:title. |
| `description(Context)` | The description, already cut to length. |
| `image(Context)` | `['url', 'width', 'height', 'alt']` or `null`: override, uploaded image, `image_fields`, generated card, default image. |
| `canonical(Context)` | The canonical URL, or `null` for none. |
| `robots(Context)` | The robots meta content. |
| `shouldNoindex(Context)` | Extra reasons to noindex (an empty listing, a thank-you page). Return `parent::shouldNoindex($context) || …`. |
| `ogType(Context)` | og:type. |
| `graph(Context)` | Every JSON-LD node. Usually you override one of the node methods instead. |
| `publisherTypes()`, `profileEntity(Context)`, `authors(Context)`, `articleImages(Context)` | The publisher's schema.org types; who a ProfilePage is about; an Article's authors; its images in three shapes. |
| `websiteNode()`, `publisherNode()`, `webPageNode(Context)`, `breadcrumbNode(Context)`, `articleNode(Context)`, `faqNode(Context)` | One node each; return `null` to leave it out. |
| `extraNodes(Context)` | Your own nodes (Product, Offer, Event…). Empty by default. |
| `additionalSitemapUrls()` | URLs that aren't entries or terms, as `[['loc' => …, 'lastmod' => …]]`. |
| `inSitemap(Entry\|Term)` | Whether a content item is listed. |
| `robotsTxt()` | robots.txt. |

Helpers available in a subclass: `settings()` (the brand global, with `string()`, `list()`, `asset()`, `siteName()`), `collectionConfig($context, $key, $default)`, and `absolute($url)`.

## The tag

`<s:seo:meta />` (Antlers: `{{ seo:meta }}`) prints every tag. It reads the entry or term from the view's `page`. Parameters:

| Parameter | |
|---|---|
| `:entry="$entry"` | The content to describe, when the view's `page` isn't it. |
| `title`, `description` | For a page without content, or to override. A `title` gets the site name added like a content title. |
| `image` | A share image URL. |
| `:canonical="false"` | No canonical tag (error pages). Or a URL. |
| `og_type` | og:type. |
| `:noindex="true"` | Noindex this page. |
| `status` | The response status; 4xx pages get noindex and no JSON-LD. |

`<s:seo:image_url />` prints only the share image's URL.

## Add a share-card template

A template turns a `Card` (title, description, label, site name, picture, colours) into a [simonhamp/the-og](https://github.com/simonhamp/the-og) image:

```php
namespace App\Og;

use JothamLec\Seo\Og\Card;
use JothamLec\Seo\Og\Template;
use SimonHamp\TheOg\Image;
use SimonHamp\TheOg\Layout\Layouts\Standard;
use SimonHamp\TheOg\Theme;

class EssayTemplate extends Template
{
    public function image(Card $card): Image
    {
        return (new Image)
            ->layout(new Standard)
            ->theme(Theme::Dark->load()->accentColor($card->accent))
            ->title($card->title)
            ->description($card->description ?? '')
            ->url($card->siteName);
    }

    public function version(): string
    {
        return '2'; // bump when the design changes, so cached cards are redrawn
    }
}
```

Register it and choose where it's used:

```php
'og' => ['templates' => ['default' => DefaultTemplate::class, 'essay' => App\Og\EssayTemplate::class]],
'collections' => ['essays' => ['og_template' => 'essay']],
```

Cards are cached per entry, last-modified time, template, version and text, and served from `/og.png` and `/og/{uri}.png` without cookies, so a CDN can cache them.

## Redirects, 404s and reports from code

- **Redirects** are the Eloquent model `JothamLec\Seo\Redirects\Redirect` (`source`, `target`, `status`, `active`, `automatic`, `hits`, `last_hit_at`). Saving or deleting one through the model clears the cached rules; after bulk queries, call `JothamLec\Seo\Redirects\Matcher::flush()`.
- **Automatic redirects** go through `JothamLec\Seo\Redirects\AutoRedirects::create($from, $to)`, which also collapses chains. Use it when you move content in code.
- **The 404 log** is `JothamLec\Seo\NotFound\MissingPath`.
- **Reports**: `app(JothamLec\Seo\Reports\Runner::class)->runToEnd($runner->start())` runs one in-process. Each check is a class in `src/Reports/Rules` extending `Rule` (`handle()`, `label()`, `weight()`, `check($url, PageFacts, SiteFacts): Result`).

## How the pieces fit

| Piece | Where |
|---|---|
| Meta tags, JSON-LD | `SiteSeo`, `Meta`, `Tags/Seo.php`, `resources/views/meta.blade.php` |
| Sitemap, robots.txt, share cards | `routes/web.php` (no session, no cookies), `Http/Controllers` |
| Redirects, 404 log | `HandleMissing`, middleware in Statamic's `statamic.web` group (only acts on 404 responses). Redirect targets get a trailing slash when Statamic adds them (`URL::enforceTrailingSlashes()`) |
| Automatic redirects | `Listeners/RedirectChangedUris` (entry, term and collection-tree events) |
| Control panel | `routes/cp.php`, `Http/Controllers/CP`, Vue in `resources/js` (built with Vite to `resources/dist`) |
| Reports | `Reports/` (Runner, Renderer, HtmlInspector, LinkChecker, Rules), `Commands/Report.php` |

## Working on the addon

```bash
composer install && npm install
npm run build      # Vue → resources/dist/build; commit the build, sites don't run npm
vendor/bin/pest    # needs PHP's imagick extension for the share-card tests
vendor/bin/pint
```

Tests run as production with an `array` cache that serializes, and render pages through `tests/fixtures/views`. Statamic matches the site by its absolute URL, so request front-end pages as `https://example.test/…`.

The suite runs on SQLite. To run it on Postgres, point it at an empty database: `SEO_TEST_DB=pgsql DB_PORT=5432 DB_DATABASE=seo_test vendor/bin/pest` (also `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`).

Release: `git tag vX.Y.Z && git push --tags`; sites update with `composer update jotham-lec/statamic-co-seo`. Note the change in [CHANGELOG.md](../CHANGELOG.md).
