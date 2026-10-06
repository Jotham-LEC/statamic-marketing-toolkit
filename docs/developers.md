# For developers

How the addon works out each value, and where to change it for one site.

## How a value is worked out

Every value the `<head>` prints comes from one public method of `JothamLec\MarketingToolkit\SiteSeo`, called with a `Context`:

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

## What each value falls back to

| Value | Order |
|---|---|
| `<title>` | SEO title as typed → the title (with **Add the site name to page titles** on: `{title}{separator}{site}` if it fits `seo.title.max`) → site name on home. `· Page N` past page 1, in the page's language |
| description | SEO description → `description` field → `description_fields` → first paragraph of `content` → global default. Cut to 155 on a word |
| share image | template `image` → SEO share image → `image_fields` (a field in a Replicator's sets too) → **generated card** (Pro) → global default image. Uploads are cropped to 1200×630 JPEG through Glide |
| canonical | template `canonical` (`false` for none) → SEO canonical (a piece first published elsewhere) → the page, with `?page=N` |
| robots | noindex when: SEO noindex, not production, a `noindex_params` query, a `noindex_routes` route, a 4xx status, or your `shouldNoindex()` |
| hreflang | the page's other languages that are published and listed, with `x-default`: `alternates()`, `localizations()`, `hreflangCodes()`, `xDefaultSite()` |
| og:locale, `inLanguage` | the content's own site's locale and language, whichever domain the request came in on |

Empty fields count as unset.

## Change a rule: SiteSeo

Extend the class, override the methods you need, and point `seo.class` at it:

```php
// app/Seo.php
namespace App;

use JothamLec\MarketingToolkit\Context;
use JothamLec\MarketingToolkit\SiteSeo;

class Seo extends SiteSeo
{
    public function extraNodes(Context $context): array
    {
        if ($context->entry?->collectionHandle() !== 'events') {
            return [];
        }

        return [[
            '@type' => 'Event',
            'name' => $context->entry->get('title'),
            'startDate' => $context->entry->date()->toAtomString(),
            'location' => ['@type' => 'Place', 'name' => $context->entry->get('venue')],
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
| `snippetRules(Context)` | The robots content after any `nofollow`: `seo.robots.default` with the page's **No snippet** or **Snippet length** applied. |
| `shouldNoindex(Context)` | Extra reasons to noindex (an empty listing, a thank-you page). Return `parent::shouldNoindex($context) || …`. |
| `hiddenOutsideProduction()` | Whether this copy of the site is kept out of search engines (every page noindexed, robots.txt disallowing all): `seo.robots.noindex_outside_production` unless `APP_ENV=production`. |
| `ogType(Context)` | og:type. |
| `graph(Context)` | Every JSON-LD node. Usually you override one of the node methods instead. |
| `publisherTypes()`, `profileEntity(Context)`, `authors(Context)`, `articleImages(Context)` | The publisher's schema.org types; who a ProfilePage is about; an Article's authors; its images in three shapes. |
| `websiteNode()`, `publisherNode()`, `webPageNode(Context)`, `breadcrumbNode(Context)`, `articleNode(Context)`, `productNode(Context)`, `faqNode(Context)` | One node each; return `null` to leave it out. Products come from a collection's `product` config. |
| `extraNodes(Context)` | Your own nodes (Event, Course…), worked out in code. Empty by default. Editors' hand-written JSON-LD, from a page's **Extra JSON-LD** field, comes from `customNodes(Context)`. |
| `additionalSitemapUrls()` | URLs that aren't entries or terms, as `[['loc' => …, 'lastmod' => …]]`. |
| `inSitemap(Entry\|Term)` | Whether a content item is listed. |
| `termHasEntries(Term)` | Whether a term has published entries, for the sitemap and the reports. Override for a taxonomy that isn't attached to the collection whose entries use it. |
| `robotsTxt()` | robots.txt. |
| `llmsPerCollection()` | How many pages llms.txt lists per collection, the most recently changed first. 100 by default. |

Helpers available in a subclass: `settings()` (the brand global, with `string()`, `list()`, `asset()`, `siteName()`), `contentConfig($context, $key, $default)` (the page's collection rules, or a term's taxonomy rules), `collectionConfig($context, $key, $default)` (an entry's collection only), and `absolute($url)`.

## The tag

`<s:seo:head />` (Antlers: `{{ seo:head }}`) prints the Consent Mode defaults, the tracking tags and the meta tags; `<s:seo:meta />` prints the meta tags alone; `<s:seo:body />` prints the tracking tags' `<noscript>` fallbacks. They read the entry or term from the view's `page`. Parameters of `seo:head` and `seo:meta`:

| Parameter | |
|---|---|
| `:entry="$entry"` | The content to describe, when the view's `page` isn't it. |
| `title`, `description` | For a page without content, or to override. A `title` gets the site name added like a content title. |
| `image` | A share image URL. |
| `:canonical="false"` | No canonical tag (error pages). Or a URL. |
| `og_type` | og:type. |
| `:noindex="true"` | Noindex this page. |
| `status` | The response status; 4xx pages get noindex and no JSON-LD. |

## Tracking from code

`JothamLec\MarketingToolkit\Tracking\Tracking` works out the tags: `ids()`, `consent()`, `posthogHost()`, `besideGtm()`, `head()` and `body()`. As with `SiteSeo`, extend it and name your class in `seo.tracking.class` to change one rule, e.g. `ids()` to read the IDs from somewhere else. See [tracking.md](tracking.md) for how the tags and Consent Mode behave.

## Add a share-card template

A template turns a `Card` (title, description, label, site name, picture, colours) into a [simonhamp/the-og](https://github.com/simonhamp/the-og) image:

```php
namespace App\Og;

use JothamLec\MarketingToolkit\Og\Card;
use JothamLec\MarketingToolkit\Og\Template;
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

- **Redirects** are the Eloquent model `JothamLec\MarketingToolkit\Redirects\Redirect` (`site`, `source`, `target`, `status`, `active`, `automatic`, `hits`, `last_hit_at`). `site` is a site handle, or null for every site (always null on a single site). Saving or deleting one through the model clears the cached rules; after bulk queries, call `JothamLec\MarketingToolkit\Redirects\Matcher::flush()`.
- **Automatic redirects** go through `JothamLec\MarketingToolkit\Redirects\AutoRedirects::create($from, $to, $site)`, which also collapses chains among that site's rules. Use it when you move content in code.
- **The 404 log** is `JothamLec\MarketingToolkit\NotFound\MissingPath` (with `site`, as redirects).
- **Reports**: `app(JothamLec\MarketingToolkit\Reports\Runner::class)->runToEnd($runner->start(site: 'handle'))` runs one in-process; without `site`, of the current site. Reports, like the 404 log, have a `site` column that is null on a single site.
- **Another site as the current one**: `JothamLec\MarketingToolkit\Support\Sites::as($handle, fn () => …)` runs code with that site current (the brand global, `absolute()`, the sitemap read it) and puts back what was there. Each check is a class in `src/Reports/Rules` extending `Rule` (`handle()`, `label()`, `weight()`, `check($url, PageFacts, SiteFacts): Result`).

## Several sites and languages

Several sites are Pro. On a multi-site install, Free looks after the default site only:

- Pages on every site keep their meta tags and structured data, but there is no hreflang.
- The sitemap lists the default site's pages alone (not its other languages, even under `/fr/` on the same domain).
- The sitemap, robots.txt, llms.txt, ads.txt and icons are served on the default site's domain only: another site's domain answers 404 for them, and its pages print no icon links. IndexNow sends only the default site's domain.
- Redirects apply on every site; a rule can't name one.
- Tools → SEO shows the default site, with a card for what Pro adds.

With Statamic Pro and more than one site, whether separate brands on their own domains or languages under `/fr/` or on their own domains, each site gets its own:

- **Brand values**: `seo:install` puts SEO & brand on every site, each other site taking what it leaves empty from the default site's. A set that already exists isn't changed: enable it on each site under **Globals → SEO & brand** (or the set's `sites`), else that site uses the addon's defaults.
- **Sitemap and robots.txt** per domain: a sitemap lists every site on its domain, each URL with its other languages. **Share cards** (Pro) and the **IndexNow key** on the site's own domain; IndexNow gets one request per domain.
- **hreflang**: a page's localizations link to each other; see [configuration.md](configuration.md#languages-hreflang).
- **Redirects** for one site or for every site (a site's own wins from the same address), and **automatic 301s** (Pro) on the site of the content that moved.
- **404 log** and **reports** (Pro), one report per site (`seo:report` reports on each in turn, or `--site=`). Tools → SEO, its screens and the dashboard widget show the site selected in the control panel.
- **Search Console property** (Pro): one key, a property per site (set up from Tools → SEO → Search Console with the site selected, or a map in config).

The SEO fields are `localizable`, so each language keeps its own values.

## Translating the control panel

Every word the addon shows in the control panel, and the "Page N" it adds to titles, is in `lang/en/*.php` under the `seo::` namespace (`cp.php`, `fields.php`, `reports.php`, `validation.php`, `frontend.php`). To translate, publish them and copy the folder:

```bash
php artisan vendor:publish --tag=seo-translations   # lang/vendor/seo/en
cp -r lang/vendor/seo/en lang/vendor/seo/fr
```

The control panel uses the user's language preference; "Page N" uses each site's language. A report shows its checks in the reader's language: results are stored as keys and translated when shown.

## How the pieces fit

| Piece | Where |
|---|---|
| Meta tags, JSON-LD | `SiteSeo`, `Meta`, `Tags/Seo.php`, `resources/views/meta.blade.php` |
| Sitemap, robots.txt, share cards | `routes/web.php` (no session, no cookies), `Http/Controllers` |
| Redirects, 404 log | `HandleMissing`, middleware in Statamic's `statamic.web` group (only acts on 404 responses, and keeps them out of Statamic's static cache, which would otherwise answer later visits without asking it). Redirect targets get a trailing slash when Statamic adds them (`URL::enforceTrailingSlashes()`) |
| Automatic redirects | `Listeners/RedirectChangedUris` (entry, term and collection-tree events) |
| Control panel | `routes/cp.php`, `Http/Controllers/CP`, Vue in `resources/js` (built with Vite to `resources/dist`) |
| Reports | `Reports/` (Runner, Renderer, HtmlInspector, LinkChecker, Rules), `Commands/Report.php` |

## Working on the addon

```bash
composer install && npm install
npm run build      # Vue → resources/dist/build; commit the build, sites don't run npm
composer test      # Pest, in parallel (about a minute); the share-card tests skip without PHP's imagick extension
composer lint      # Pint; `vendor/bin/pint` fixes what it finds
composer analyse   # Larastan, level 5, with the 1 GB it needs; phpstan.neon says why each ignored error is ignored
```

Tests run as production with an `array` cache that serializes, and render pages through `tests/fixtures/views`. Statamic matches the site by its absolute URL, so request front-end pages as `https://example.test/…`.

The suite runs on SQLite. To run it on Postgres, point it at an empty database: `SEO_TEST_DB=pgsql DB_PORT=5432 DB_DATABASE=seo_test vendor/bin/pest` (also `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`; not in parallel, as the processes would share the database). GitHub Actions runs all of this on every push: PHP 8.3 on the oldest versions composer.json allows, PHP 8.4 on the newest, Postgres, and without Imagick.

Tests run in the Pro edition (`TestCase::edition()`); a file that tests Free uses the `JothamLec\MarketingToolkit\Tests\FreeEdition` trait: `uses(FreeEdition::class)`.

Release:

1. Note the change in [CHANGELOG.md](../CHANGELOG.md).
2. `npm run build` and commit `resources/dist`.
3. `git tag -a vX.Y.Z -m vX.Y.Z && git push --follow-tags`. Sites update with `composer update jotham-lec/statamic-marketing-toolkit`; the Marketplace picks the tag up from Packagist.
