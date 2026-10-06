# Configuration

Two places, by who changes what:

- **`config/seo.php`**: rules that belong in code and git (which field is the description, the schema type, redirects and the 404 log). Publish it with `php artisan vendor:publish --tag=seo-config`; anything you leave out keeps its default, at any depth: set `og.templates` alone and `og.enabled` stays. A list you set (`not_found.ignore_paths`, `sitemap.collections`) replaces the default list; copy the defaults in if you want to add to them.
- **Tools → SEO → Report settings** (Statamic's addon settings for Marketing Toolkit, Pro): report settings an editor may want to change.

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
| `title.max` | `60` | With **Add the site name to page titles** on, `{title}{separator}{site name}` is used only if it fits; otherwise the title alone. |
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
| `not_found.max_rows` | `1000` | Most paths kept, give or take a tenth: the log is trimmed now and then, not on every new path. One-off misses (one hit, no page linking there) go first, then the least recently seen, so a flood of made-up addresses can't push out real broken links. |
| `not_found.ignore_user_agents` | bots, crawlers, curl, wget… | Not logged when the user agent contains one of these (any case). |
| `not_found.ignore_paths` | `*.php`, `/wp-*`, `/.env*`, `/.git*`… | Not logged when the path matches one (`*` matches anything). Scanner probes, mostly. |


### Languages (hreflang)

With several sites (Statamic Pro and Marketing Toolkit Pro), a page links to itself in each other language: a `<link rel="alternate" hreflang>` tag per language in the `<head>`, an `og:locale:alternate` for each, and the same links as `<xhtml:link>` in the sitemap. The languages of one page are its entry's origin and the entries localized from it, or a term on each site of its taxonomy that has entries there. A version that is a draft, noindexed, left out of the sitemap or canonical elsewhere is left out; a page that is one of those itself gets no tags. The code is the site's language (`fr`), or its full locale (`en-GB`, `en-US`) where two sites share a language.

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

**From the control panel**: **Tools → SEO → Search Console** walks whoever may change the addon's settings through it: the Google Cloud and Search Console steps with their links, uploading the key, the property (the site's domain is suggested), a check that turns Google's refusals into what to fix, and the first import. The key is kept in `storage/app/private/seo/search-console-key.json`, encrypted with `APP_KEY` (never in git; on a deployed site, keep `storage` between releases, as Laravel expects; if `APP_KEY` changes, upload the key again), the property as the addon setting `search_console_property`. **From `.env`**, as below; a value there wins and the control panel shows it without changing it.

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

## Features (Pro)

**Tools → SEO → Features** has a switch per module, for whoever may change the addon's settings: the sitemap, robots.txt, llms.txt, hreflang, IndexNow, generated share cards, redirects, redirects when a page moves, the 404 log, scheduled reports, tracking and Consent Mode, leads, favicons and ads.txt. What's off is saved in the addon settings (`features_off`) and set off in the config at boot, before anything registers: its routes answer 404, and its listeners and middleware aren't loaded, so it costs nothing on a request. Nothing it saved is deleted.

Since the switches apply at boot, a process that boots once and serves many requests or jobs (Laravel Octane, a queue worker, Horizon) picks up a change when it restarts: run `php artisan octane:reload` or `php artisan queue:restart` after switching a module on or off. A PHP-FPM site picks it up from the next request.

Each switch sets the matching key below to off, whatever `config/seo.php` says. A module `config/seo.php` already switches off shows off on the screen, locked, with "Off in config/seo.php": a switch can't turn it back on. In Free, the screen isn't there and every module follows `config/seo.php`.

## Editions

`config/statamic/editions.php`, `'addons' => ['jotham-lec/statamic-marketing-toolkit' => 'pro']`, turns on Pro. Without it the addon runs as Free, which forces `og.enabled`, `redirects.automatic` and `not_found.enabled` off whatever `config/seo.php` says, works with the default site alone on a multi-site install (no hreflang; a sitemap, robots.txt and IndexNow for the default site's domain only; other domains answer 404 for them), leaves out Search Console, the reports and their settings, the 404 log, CSV import and export, the widget and the Pro commands, and answers Pro's control panel addresses with a 404. Statamic's own `'pro' => true` in the same file is Statamic CMS Pro, a separate thing that several sites need.

## Tracking

Each ID can be set in the **Tracking** tab of SEO & brand, or here, which wins (and shows as "set in .env" on Tools → SEO). See [tracking.md](tracking.md).

| Key | `.env` | |
|---|---|---|
| `tracking.gtm` | `SEO_GTM_ID` | Google Tag Manager container, `GTM-XXXXXXX`. |
| `tracking.ga4` | `SEO_GA4_ID` | Google Analytics 4 measurement ID, `G-XXXXXXXXXX`. |
| `tracking.posthog_key` | `SEO_POSTHOG_KEY` | PostHog project API key, `phc_…`. |
| `tracking.posthog_host` | `SEO_POSTHOG_HOST` | PostHog's API host: `https://eu.i.posthog.com` for an EU project; `https://us.i.posthog.com` unless set. |
| `tracking.meta_pixel` | `SEO_META_PIXEL_ID` | Meta Pixel ID (digits). |
| `tracking.linkedin` | `SEO_LINKEDIN_PARTNER_ID` | LinkedIn Insight Tag partner ID (digits). |
| `tracking.enabled` | | `true`. Off: no tags, Consent Mode or leads. |
| `tracking.environments` | | `['production']`: the environments the tags print in. Never in Live Preview. |
| `leads.enabled` | | `true` (Pro). Off: no form submission is sent as a lead or saved with where it came from. See [tracking.md](tracking.md#leads-pro). |

An ID that doesn't look like one (`GTM-` and letters or digits, and so on) is never printed. Consent Mode is set in the global only.

## llms.txt and ads.txt

| Key | Default | |
|---|---|---|
| `llms_txt` | `true` | `/llms.txt` ([llmstxt.org](https://llmstxt.org)): the site's name and default description, then, for each collection the sitemap lists, its 100 most recently changed pages as Markdown links with their descriptions. Cached until content changes, like the sitemap. Override `llmsTxt()` in your `SiteSeo` subclass to write it differently. |
| `ads_txt` | `true` | `/ads.txt`: the lines in **SEO & brand → Crawlers → ads.txt**; a 404 while that's empty. |

A file of the same name in `public/` wins over either.

## Favicons

| Key | Default | |
|---|---|---|
| `favicons.enabled` | `true` | Make the icons from **Icon** in SEO & brand, serve them, and print their links in `<s:seo:head />` (or `<s:seo:favicons />`). |

From one image the addon makes `/favicon.ico` (16, 32 and 48 px), `/favicon.svg` (an SVG upload, as it is), `/apple-touch-icon.png` (180 px, on the icon background), `/icon-192.png`, `/icon-512.png` and `/site.webmanifest` (the site's name, short name and colours). They're made once per version of the image and colours, kept in `storage/app/marketing-toolkit/favicons`, made again when SEO & brand is saved, and served without a session or cookie. A file of the same name in `public/` wins, so delete old ones there. Drawing SVG needs PHP's Imagick; with GD alone an SVG gives `/favicon.svg` and the manifest, so upload a PNG on such hosts.

## Report settings (Pro)

**Tools → SEO → Report settings** (Statamic's addon settings for Marketing Toolkit), saved as YAML in `resources/addons/marketing-toolkit.yaml` (or wherever your site stores addon settings). On a site where the production control panel is where content lives, keep that file out of deploys, or store addon settings in the database, so a deploy doesn't overwrite them.

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
| Run a report | Only by hand | Or daily or weekly, on the day and at the time you choose (app timezone). Needs the scheduler. `seo.reports.enabled` (or the Features switch) off stops the schedule. |

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

## What it sends where

The addon sends nothing to its author: no licence check, no usage numbers, no updates check. The server or the visitor's browser talks to another service only for a feature that is on and set up:

| When | From | To | What is sent |
|---|---|---|---|
| Published content is saved, goes live or is deleted (IndexNow on, production only) | The server | `https://api.indexnow.org/indexnow` | The site's host, the IndexNow key, where the key file is, and the changed addresses. |
| A Search Console check or import (Pro, with a key and a property) | The server | `https://oauth2.googleapis.com/token`, then `https://www.googleapis.com/webmasters/v3/sites/…` | A token request signed with the service account key (its email, the read-only Search Console scope, an expiry; the private key itself never leaves the server); then the property, and for an import the date range and which rows. |
| A report runs with **Check external links** on (Pro, off unless switched on in the report settings) | The server | The sites the pages link to | A `HEAD` request (a `GET` where `HEAD` is refused) for each linked address, at most 50 a page, each answer kept for a day. Addresses on this machine or a private network are never asked. |
| A visitor opens a page, with a tracking ID set, in the environments in `tracking.environments` (production unless changed), never in Live Preview | The visitor's browser | Google Tag Manager and Google Analytics (`googletagmanager.com`), the Meta Pixel (`connect.facebook.net`, `facebook.com`), LinkedIn (`snap.licdn.com`, `px.ads.linkedin.com`), PostHog (your `tracking.posthog_host`) | Whatever each tool's own script collects, and with leads on (Pro), a lead event when a form is sent. How each behaves before consent: [tracking.md](tracking.md#meta-linkedin-and-posthog-without-gtm-pro). |

Reports render pages and check internal links inside the application, without a request over the network.
