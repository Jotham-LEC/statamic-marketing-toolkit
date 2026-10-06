# Configuration

Two places, by who changes what:

- **`config/seo.php`**: rules that belong in code and git (which field is the description, the schema type, redirects and the 404 log). Publish it with `php artisan vendor:publish --tag=seo-config`; anything you leave out keeps its default, at any depth: set `og.templates` alone and `og.enabled` stays. A list you set (`not_found.ignore_paths`, `sitemap.collections`) replaces the default list; copy the defaults in if you want to add to them.
- **Tools → Addons → SEO** (Statamic's addon settings): report settings an editor may want to change.

The site's name is Statamic's own (Settings → Sites, else `APP_NAME`). Brand details (separator, logo, colours, verification codes) are content, edited under **Globals → SEO & brand**; see [editors.md](editors.md#seo--brand).

## config/seo.php

### Rules class

| Key | Default | |
|---|---|---|
| `class` | `JothamLec\Seo\SiteSeo` | The class that works out every value. Extend it to change one rule; see [developers.md](developers.md#change-a-rule-siteseo). |
| `global` | `'seo'` | Handle of the brand global set. |

### Titles and descriptions

| Key | Default | |
|---|---|---|
| `title.max` | `60` | `{title}{separator}{site name}` is used only if it fits; otherwise the title alone. |
| `description.length` | `155` | A description taken from the page is cut to this, on a word. |

### Collections

`collections` maps a collection handle to its rules. Every key is optional.

```php
'collections' => [
    'essays' => [
        'og_type' => 'article',            // og:type (default 'website')
        'schema' => 'Article',             // adds an Article, NewsArticle or BlogPosting node
        'page_schema' => 'WebPage',        // the WebPage node's type (CollectionPage, ProfilePage: about the entry, as a Person)
        'description_fields' => ['intro'], // tried before the body's first paragraph
        'image_fields' => ['hero'],        // tried before the generated card
        'faq_field' => 'faqs',             // a grid of question / answer rows → FAQPage (valid markup; Google shows no FAQ results since 2026)
        'author_field' => 'authors',       // an entries or users field → the Article's authors (else the publisher)
        'product' => [                     // a Product + Offer from these fields (needs a price above 0 and a currency)
            'price_field' => 'price', 'availability_field' => 'in_stock', // a toggle, or InStock/PreOrder…
            'sku_field' => 'sku', 'gtin_field' => null, 'brand_field' => null, 'brand' => 'Acme',
            'currency' => null,            // else the SEO & brand global's Shop currency
            'condition' => 'NewCondition',
        ],
        'og_template' => 'default',        // a key of og.templates
    ],
],
```

### Taxonomies

`taxonomies` gives a taxonomy's term pages the same rules, by taxonomy handle: `og_type`, `page_schema`, `description_fields`, `image_fields` and `faq_field`. A term has none of its collections' rules.

```php
'taxonomies' => [
    'topics' => [
        'page_schema' => 'CollectionPage', // a listing of the topic's pages
        'description_fields' => ['intro'],
        'image_fields' => ['banner'],
    ],
],
```

Terms are listed in the sitemap (and checked by reports) when their taxonomy is in `sitemap.taxonomies` and they have published entries. Statamic counts only entries of the collections a taxonomy is attached to; when entries name their terms in a `terms` field of a taxonomy that isn't attached, override `termHasEntries()` in your `SiteSeo` subclass.

### Robots

| Key | Default | |
|---|---|---|
| `robots.noindex_outside_production` | `true` | Every page is `noindex` unless `APP_ENV=production`, so staging copies stay out of search results. |
| `robots.noindex_params` | `[]` | A request with any of these query parameters is noindexed (filtered or sorted listings), e.g. `['sort', 'tag']`. |
| `robots.noindex_routes` | `[]` | Route names to noindex, e.g. `['thank-you']`. |
| `robots.default` | `'max-snippet:-1, max-image-preview:large, max-video-preview:-1'` | The robots tag on pages that are indexed. |

### Sitemap and robots.txt

| Key | Default | |
|---|---|---|
| `sitemap.enabled` | `true` | Serves `/sitemap.xml`. |
| `sitemap.collections` | `null` | `null`: every collection with a route. Or a list of handles. |
| `sitemap.exclude_collections` | `[]` | Left out even when `collections` is `null`. |
| `sitemap.taxonomies` | `[]` | Taxonomies whose terms are listed (only terms with published entries). Reports check these terms too. |
| `sitemap.per_page` | `1000` | Above this, `/sitemap.xml` becomes an index of `/sitemap_1.xml`, `/sitemap_2.xml`… |
| `robots_txt` | `true` | Serves `/robots.txt` from the global. A real `public/robots.txt` wins. |

The sitemap lists only canonical addresses: it leaves out drafts, redirect entries, noindexed pages, pages whose canonical points to another page (on this site or another), and pages with "In sitemap" off. It's cached and rebuilt when content is saved or deleted, when a collection, taxonomy or page tree is saved, when `seo.sitemap` changes, when the Stache is cleared (as a deploy does, so changed rules show at once), and when a scheduled entry's date arrives (that needs Laravel's scheduler running, as Statamic's scheduled entries do).

### Redirects and the 404 log

| Key | Default | |
|---|---|---|
| `redirects.enabled` | `true` | Applies the rules under Tools → SEO → Redirects. |
| `redirects.automatic` | `true` | Adds a 301 when published content moves (slug, date, place in a tree). |
| `redirects.case_sensitive` | `true` | `false` matches a redirect's From in any letter case, accents and other alphabets included: `/ABOUT-US` and `/About-Us` as `/about-us`, `/CAFÉ` as `/café`. What a `*` matched keeps the visitor's case. Two redirects whose From differs only in case are then refused as the same address, and a CSV row updates the redirect with that From in any case. For a site moved off one whose addresses worked in any case (Wix, IIS). |
| `not_found.enabled` | `true` | Logs 404s. |
| `not_found.max_rows` | `1000` | Most paths kept. One-off misses (one hit, no page linking there) go first, then the least recently seen, so a flood of made-up addresses can't push out real broken links. |
| `not_found.ignore_user_agents` | bots, crawlers, curl, wget… | Not logged when the user agent contains one of these (any case). |
| `not_found.ignore_paths` | `*.php`, `/wp-*`, `/.env*`, `/.git*`… | Not logged when the path matches one (`*` matches anything). Scanner probes, mostly. |


### IndexNow

| Key | Default | |
|---|---|---|
| `indexnow.enabled` | `true` | When published content is saved, goes live on schedule or is deleted, its address is sent to IndexNow (Bing, Yandex, Naver, Seznam and others; not Google) once the request has been answered. Production only; a failure is logged. |
| `indexnow.key` | `null` (`SEO_INDEXNOW_KEY`) | The key served at `/{key}.txt`. Left empty, it is derived from `APP_KEY`, so it stays the same across deploys. |

### Google Search Console

Clicks, impressions, click-through rate and average position per page, imported daily and shown on Tools → SEO. Off until there is a key and a property.

**From the control panel**: Tools → SEO walks whoever may change the addon's settings through it: the Google Cloud and Search Console steps with their links, uploading the key, the property (the site's domain is suggested), a check that turns Google's refusals into what to fix, and the first import. The key is kept in `storage/app/private/seo/search-console-key.json` (never in git; on a deployed site, keep `storage` between releases, as Laravel expects), the property as the addon setting `search_console_property`. **From `.env`**, as below; a value there wins and the control panel shows it without changing it.

| Key | Default | |
|---|---|---|
| `search_console.credentials` | `SEO_SEARCH_CONSOLE_CREDENTIALS` | A service account's JSON key, or the path to the file. |
| `search_console.property` | `SEO_SEARCH_CONSOLE_PROPERTY` | The property as Search Console names it: `sc-domain:example.com` for a domain property, `https://example.com/` for a URL prefix. With several sites, a string is every site's; in `config/seo.php` it can be a map, `['default' => 'sc-domain:example.com', 'shop' => 'sc-domain:shop.example']`. |
| `search_console.days` | `28` | The period imported, ending today (Pacific time, as Search Console counts). |

Setting it up:

1. In [Google Cloud](https://console.cloud.google.com/), create a project (or use one), enable the **Google Search Console API**, and create a **service account** with a **JSON key**.
2. In [Search Console](https://search.google.com/search-console), open the property → Settings → Users and permissions, and add the service account's email (`…@….iam.gserviceaccount.com`) as a **Restricted** user.
3. Put the key on the server (outside the web root) and set `SEO_SEARCH_CONSOLE_CREDENTIALS=/path/to/key.json` and `SEO_SEARCH_CONSOLE_PROPERTY` in `.env`.
4. Run `php please seo:search-console` once (or Import now on Tools → SEO); the schedule then runs it daily at 04:30 (Laravel's scheduler must be running).

### Share cards and images

| Key | Default | |
|---|---|---|
| `og.enabled` | `true` | Generated cards at `/og.png` (home) and `/og/{uri}.png`. |
| `og.templates` | `['default' => DefaultTemplate::class]` | Card designs by key; see [developers.md](developers.md#add-a-share-card-template). |
| `og.max_age` | 30 days | `Cache-Control` max-age of the card images. |

Uploaded share images are cropped to 1200×630 and served as JPEG; for another size, override `imageWidth()` and `imageHeight()` in your `SiteSeo` subclass.

## Addon settings: Tools → Addons → SEO

Saved as YAML in `resources/addons/seo.yaml` (or wherever your site stores addon settings). On a site where the production control panel is where content lives, keep that file out of deploys, or store addon settings in the database, so a deploy doesn't overwrite them.

**Checks** tab:

| Setting | Default | |
|---|---|---|
| A switch per check | all on | A check that's off is left out of the reports and the scores. |
| Title: at least / at most | 30 / 60 | Thresholds for the title check and the preview's title counter. |
| Description: at least / at most | 50 / 160 | The same for descriptions. |

**Running** tab:

| Setting | Default | |
|---|---|---|
| Leave out these collections | none | Their entries aren't checked. |
| Most pages per report | 0 (all) | Stops after this many pages. |
| Pages per step | 25 | Pages one step renders. Lower it if a step times out. |
| Reports to keep | 10 | Older reports are deleted when a new one finishes. |
| Run a report | Only by hand | Or daily or weekly, on the day and at the time you choose (app timezone). Needs the scheduler. |

**Search Console** tab: the **Property**, as set from Tools → SEO, and with several sites the other sites' properties by handle. `SEO_SEARCH_CONSOLE_PROPERTY` wins over them.

## Permissions

| Permission | |
|---|---|
| `view seo` | Tools → SEO, reports, the 404 log, the widget. |
| `manage seo redirects` | Create, edit, import, export and delete redirects; delete 404 rows; the "add a redirect?" question when saving. |
| `run seo reports` | Start a report. |

## Commands

| Command | |
|---|---|
| `php please seo:install [--container=] [--fields] [--tab=shop]` | `--fields` adds to an existing SEO & brand blueprint the fields a newer version brings, in the tabs it still has; `--tab` adds a whole tab it doesn't have (`shop`, `publisher`…). |
| `php please seo:install [--container=]` | Creates the SEO & brand global set and its blueprint, and fills its empty brand fields with what the site uses (separator, the home page's description, the robots.txt rule). Never overwrites a value. |
| `php please seo:search-console [--site=]` | Imports the last period's numbers from Google Search Console. With several sites, each site that has a property, or only `--site`. |
| `php please seo:report [--site=]` | Runs a whole report in the terminal and prints the scores. Continues a report that's already running. With several sites, one report per site in turn, or only `--site`; the schedule runs one per site. |
