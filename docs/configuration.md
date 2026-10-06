# Configuration

Two places, by who changes what:

- **`config/seo.php`**: rules that belong in code and git (which field is the description, the schema type, redirects and the 404 log). Publish it with `php artisan vendor:publish --tag=seo-config`; anything you leave out keeps its default, at any depth: set `og.templates` alone and `og.enabled` stays. A list you set (`not_found.ignore_paths`, `sitemap.collections`) replaces the default list; copy the defaults in if you want to add to them.
- **Tools → Addons → SEO** (Statamic's addon settings): report settings an editor may want to change.

The site's name is Statamic's own (Settings → Sites, else `APP_NAME`). Brand details (separator, logo, colours, verification codes) are content, edited under **Globals → SEO & brand**; see [editors.md](editors.md#seo--brand).

## config/seo.php

### Rules class

| Key | Default | |
|---|---|---|
| `class` | `JothamLec\MarketingToolkit\SiteSeo` | The class that works out every value. Extend it to change one rule; see [developers.md](developers.md#change-a-rule-siteseo). |
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

#### Fields in a page builder

`description_fields`, `image_fields` and `faq_field` can name a field inside a Replicator's sets as `replicator.set.field`: the Replicator's handle, the set's type (`*` for any) and the field in the set. Statamic handles have no dots, so a plain name means what it always did.

```php
'pages' => [
    'description_fields' => ['sections.hero.lead'], // the first visible hero set with a lead
    'image_fields' => ['sections.hero.image'],      // the first visible hero set with a photo
    'faq_field' => 'sections.faq.faqs',             // the questions of every visible FAQ set, in the page's order
],
```

A set switched off in the control panel isn't on the page, so it counts for nothing.

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
| `redirects.automatic` | `true` | Adds a 301 when published content moves (slug, date, place in a tree). Pro: always off in Free. |
| `redirects.case_sensitive` | `true` | `false` matches a redirect's From in any letter case, accents and other alphabets included: `/ABOUT-US` and `/About-Us` as `/about-us`, `/CAFÉ` as `/café`. What a `*` matched keeps the visitor's case. Two redirects whose From differs only in case are then refused as the same address, and a CSV row updates the redirect with that From in any case. For a site moved off one whose addresses worked in any case (Wix, IIS). |
| `not_found.enabled` | `true` | Logs 404s. Pro: always off in Free. |
| `not_found.max_rows` | `1000` | Most paths kept. One-off misses (one hit, no page linking there) go first, then the least recently seen, so a flood of made-up addresses can't push out real broken links. |
| `not_found.ignore_user_agents` | bots, crawlers, curl, wget… | Not logged when the user agent contains one of these (any case). |
| `not_found.ignore_paths` | `*.php`, `/wp-*`, `/.env*`, `/.git*`… | Not logged when the path matches one (`*` matches anything). Scanner probes, mostly. |


### Languages (hreflang)

With several sites (Statamic Pro), a page links to itself in each other language: a `<link rel="alternate" hreflang>` tag per language in the `<head>`, an `og:locale:alternate` for each, and the same links as `<xhtml:link>` in the sitemap. The languages of one page are its entry's origin and the entries localized from it, or a term on each site of its taxonomy that has entries there. A version that is a draft, noindexed, left out of the sitemap or canonical elsewhere is left out; a page that is one of those itself gets no tags. The code is the site's language (`fr`), or its full locale (`en-GB`, `en-US`) where two sites share a language.

| Key | Default | |
|---|---|---|
| `hreflang.enabled` | `true` | Off: no hreflang tags or sitemap links. Turn it off for sites that are separate brands rather than languages, whose terms would otherwise point at each other. |
| `hreflang.x_default` | `null` | The site whose version is `x-default`, what everyone else gets: `null` for the default site, a site handle, or `false` for none. |

A sitemap lists every site on its domain: languages under `/fr/` are in `example.com/sitemap.xml`; a language on its own domain has its own.

### IndexNow

| Key | Default | |
|---|---|---|
| `indexnow.enabled` | `true` | When published content is saved, goes live on schedule or is deleted, its address is sent to IndexNow (Bing, Yandex, Naver, Seznam and others; not Google) once the request has been answered. Production only; a failure is logged. |
| `indexnow.key` | `null` (`SEO_INDEXNOW_KEY`) | The key served at `/{key}.txt`. Left empty, it is derived from `APP_KEY`, so it stays the same across deploys. |

### Google Search Console (Pro)

Clicks, impressions, click-through rate and average position per page, imported daily and shown on Tools → SEO. Off until there is a key and a property.

**From the control panel**: **Tools → SEO → Search Console** walks whoever may change the addon's settings through it: the Google Cloud and Search Console steps with their links, uploading the key, the property (the site's domain is suggested), a check that turns Google's refusals into what to fix, and the first import. The key is kept in `storage/app/private/seo/search-console-key.json` (never in git; on a deployed site, keep `storage` between releases, as Laravel expects), the property as the addon setting `search_console_property`. **From `.env`**, as below; a value there wins and the control panel shows it without changing it.

| Key | Default | |
|---|---|---|
| `search_console.credentials` | `SEO_SEARCH_CONSOLE_CREDENTIALS` | A service account's JSON key, or the path to the file. |
| `search_console.property` | `SEO_SEARCH_CONSOLE_PROPERTY` | The property as Search Console names it: `sc-domain:example.com` for a domain property, `https://example.com/` for a URL prefix. With several sites, a string is every site's; in `config/seo.php` it can be a map, `['default' => 'sc-domain:example.com', 'shop' => 'sc-domain:shop.example']`. |
| `search_console.days` | `28` | The period imported, ending today (Pacific time, as Search Console counts). |

Setting it up:

1. In [Google Cloud](https://console.cloud.google.com/), create a project (or use one), enable the **Google Search Console API**, and create a **service account** with a **JSON key**.
2. In [Search Console](https://search.google.com/search-console), open the property → Settings → Users and permissions, and add the service account's email (`…@….iam.gserviceaccount.com`) as a **Restricted** user.
3. Put the key on the server (outside the web root) and set `SEO_SEARCH_CONSOLE_CREDENTIALS=/path/to/key.json` and `SEO_SEARCH_CONSOLE_PROPERTY` in `.env`.
4. Run `php please seo:search-console` once (or Import now on Tools → SEO → Search Console); the schedule then runs it daily at 04:30 (Laravel's scheduler must be running).

**Can't create a key, or Google says it is disabled?** New Google Cloud projects often have service account keys blocked by an organization policy, `iam.disableServiceAccountKeyCreation`. Someone who administers the organization can allow keys for the project in [Organization policies](https://console.cloud.google.com/iam-admin/orgpolicies/iam-disableServiceAccountKeyCreation). A key that exists but is disabled can be [enabled again](https://docs.cloud.google.com/iam/docs/keys-disable-enable); the check on the Search Console screen says when Google reports a disabled key or account.

### Share cards and images

| Key | Default | |
|---|---|---|
| `og.enabled` | `true` | Generated cards at `/og.png` (home) and `/og/{uri}.png`. Pro: always off in Free. |
| `og.templates` | `['default' => DefaultTemplate::class]` | Card designs by key; see [developers.md](developers.md#add-a-share-card-template). |
| `og.max_age` | 30 days | `Cache-Control` max-age of the card images. |

Uploaded share images are cropped to 1200×630 and served as JPEG; for another size, override `imageWidth()` and `imageHeight()` in your `SiteSeo` subclass.

## Editions

`config/statamic/editions.php`, `'addons' => ['jotham-lec/statamic-marketing-toolkit' => 'pro']`, turns on Pro. Without it the addon runs as Free, which forces `og.enabled`, `redirects.automatic` and `not_found.enabled` off whatever `config/seo.php` says, leaves out Search Console, the reports and their settings, the 404 log, CSV import and export, the widget and the Pro commands, and answers Pro's control panel addresses with a 404. Statamic's own `'pro' => true` in the same file is Statamic CMS Pro, a separate thing that several sites need.

## Addon settings: Tools → Addons → SEO (Pro)

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

The Search Console property is kept here too (`search_console_property`, and `search_console_properties` for the other sites), but set on **Tools → SEO → Search Console**. `SEO_SEARCH_CONSOLE_PROPERTY` wins over them.

## Permissions

| Permission | |
|---|---|
| `view seo` | Tools → SEO; with Pro, reports, the 404 log, the Search Console screen (changing the connection needs permission to change the addon's settings), the widget. |
| `manage seo redirects` | Create, edit and delete redirects; with Pro, import and export them, delete 404 rows, and the "add a redirect?" question when saving. |
| `run seo reports` | Start a report (Pro). |

## Commands

| Command | |
|---|---|
| `php please seo:install [--container=] [--fields] [--tab=shop]` | `--fields` adds to an existing SEO & brand blueprint the fields a newer version brings, in the tabs it still has; `--tab` adds a whole tab it doesn't have (`shop`, `publisher`…). |
| `php please seo:install [--container=]` | Creates the SEO & brand global set and its blueprint, and fills its empty brand fields with what the site uses (separator, the home page's description, the robots.txt rule). Never overwrites a value. |
| `php please seo:search-console [--site=]` (Pro) | Imports the last period's numbers from Google Search Console. With several sites, each site that has a property, or only `--site`. |
| `php please seo:report [--site=]` (Pro) | Runs a whole report in the terminal and prints the scores. Continues a report that's already running. With several sites, one report per site in turn, or only `--site`; the schedule runs one per site. |
