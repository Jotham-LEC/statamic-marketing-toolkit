# Changelog

## Unreleased (0.18.0)

Co-SEO is now **Marketing Toolkit**: the marketing fundamentals of a website, not only its SEO.

### Upgrading
- **New package name.** `composer remove jotham-lec/statamic-co-seo --no-update && composer require jotham-lec/statamic-marketing-toolkit`, then rename the key in `config/statamic/editions.php` to `jotham-lec/statamic-marketing-toolkit`. Everything else stays: see [Upgrading from Co-SEO](docs/upgrading.md).
- PHP classes moved from `JothamLec\Seo` to `JothamLec\MarketingToolkit`.
- For tracking: swap `<s:seo:meta />` for `<s:seo:head />` at the top of the `<head>`, add `<s:seo:body />` after `<body>`, remove the site's own tracking snippets, and run `php please seo:install --fields --tab=tracking` (it also adds the icon fields).

### Added
- **Tracking.** Google Tag Manager, Google Analytics 4, PostHog, the Meta Pixel and the LinkedIn Insight Tag, from a new **Tracking** tab of SEO & brand (`php please seo:install --tab=tracking` on an existing site) or `config/seo.php` / `.env` (`SEO_GTM_ID`, `SEO_GA4_ID`, `SEO_POSTHOG_KEY`, `SEO_POSTHOG_HOST`, `SEO_META_PIXEL_ID`, `SEO_LINKEDIN_PARTNER_ID`), which win. In production only, never in Live Preview; IDs that don't look like IDs are never printed; Vite's CSP nonce is added. New tags `<s:seo:head />` (consent defaults and tags first, then the meta tags) and `<s:seo:body />` (the `<noscript>` fallbacks); `<s:seo:meta />` is unchanged. With GTM and another tool both set, the Tracking tab, the save and Tools → SEO warn that each visit may count twice.
- **Consent Mode v2** for an existing cookie banner: the four signals' defaults, `wait_for_update`, and *(Pro)* regions where they apply (with an "EEA, UK and Switzerland" choice). A consent bridge holds back Meta, LinkedIn and PostHog loaded directly until the banner grants consent. See [docs/tracking.md](docs/tracking.md).

- **Leads** *(Pro)*: a Statamic form submission is sent to each tool as a lead (`generate_lead` for GTM and GA4, `Lead` for Meta, `form submitted` for PostHog, a LinkedIn conversion ID), through a short-lived `mt_conversion` cookie that works with static caching; `window.mtConversion(form)` for other forms. **Lead source** (off until turned on): the first visit's UTM tags, referrer and landing page, kept in an `mt_source` cookie (after consent, with Consent Mode) and saved with each submission in fields `php please seo:install --forms` adds to every form.
- **Campaign links** *(Pro)*: a redirect's form has UTM fields (source, medium, campaign, content, term) that are added to its target's query string, so a short address like `/go/linkedin` carries the campaign; the redirect counts the clicks.
- **/llms.txt**: the site's name and description, then each sitemap collection's pages as Markdown links with their descriptions (`seo.llms_txt`, `SiteSeo::llmsTxt()`), cached until content changes. **/ads.txt** from a new **ads.txt** field in SEO & brand → Crawlers (`seo.ads_txt`). Both Free; a file in `public/` wins.
- **Features** *(Pro)*: Tools → SEO → Features switches modules off (sitemap, robots.txt, llms.txt, hreflang, IndexNow, share cards, redirects, automatic redirects, the 404 log, the weekly link check, tracking, leads, favicons, ads.txt). A module that's off is off in the config before anything registers: its routes answer 404 and its listeners and middleware aren't loaded. New config keys `seo.tracking.enabled` and `seo.leads.enabled`.
- **Favicons.** From one image in SEO & brand (new **Icon**, **Theme colour** and **Icon background** fields; `php please seo:install --fields`): `/favicon.ico`, `/favicon.svg`, `/apple-touch-icon.png`, `/icon-192.png`, `/icon-512.png` and `/site.webmanifest`, with their `<link>` tags and `theme-color` in `<s:seo:head />` (or `<s:seo:favicons />`). Made with Imagick or GD, cached, made again on save, served without a session. A file in `public/` wins. `seo.favicons.enabled`.

### Changed
- **Reports are now the link check** (Pro): every week it opens every page and lists broken links (on the site and to other sites, now on by default) and pages with no description or share image. No more scores, weights or settings screen: `seo.reports.schedule` (`'weekly'`, `'daily'` or `false`), `seo.reports.external_links` and `seo.reports.exclude_collections` in `config/seo.php` replace Tools → Addons → SEO. The title, unique title and description, h1, canonical, hidden-page-in-sitemap, image alt, JSON-LD and orphan-page checks are gone: Search Console reports indexing and structured data problems, and the preview's counters catch lengths as editors type. A migration clears the old reports (the tables stay). The preview's counters use fixed targets (title 30–60 or `seo.title.max`, description 50–160).
- **Several sites and hreflang are Pro.** On a multi-site install, Free looks after the default site: pages on every site keep their meta tags, but there is no hreflang or `og:locale:alternate`, the sitemap lists the default site alone, other domains answer 404 for `sitemap.xml` and `robots.txt`, IndexNow sends the default domain's pages only, and Tools → SEO shows the default site with a card for Pro. Multi-site needs Statamic Pro anyway; set the addon to Pro to keep everything.
- The addon's slug is `marketing-toolkit`: its scripts are published to `public/vendor/statamic-marketing-toolkit` (tag `marketing-toolkit`), and its settings file is `resources/addons/marketing-toolkit.yaml`, copied once from `seo.yaml`. `config/seo.php`, the `seo::` views, translations and fieldsets, routes, permissions and tags keep their names.

## 0.17.0 – 2026-10-06

Co-SEO comes in two editions, Free and Pro; pages link to their other languages (hreflang); and every word in the control panel can be translated. On the Statamic Marketplace from this version.

### Upgrading
- **Keep every feature: set Pro.** A site that updates without it runs as Free, which turns off Search Console, reports, generated share cards, automatic 301s, the 404 log, CSV import and export, and the dashboard widget. In `config/statamic/editions.php`:
  ```php
  'addons' => ['jotham-lec/statamic-co-seo' => 'pro'],
  ```
  A live site on Pro needs a licence from the [Marketplace](https://statamic.com/addons/jothamlec/co-seo). Nothing is deleted either way: reports, the 404 log and Search Console's numbers stay in their tables, and come back with Pro.
- **Several languages on one domain**: a sitemap now lists every site on its domain, so `example.com/sitemap.xml` includes the pages under `/fr/`. Sites on their own domains are unchanged.
- **SEO fields per language**: the `seo` fieldset's fields are now `localizable`. A localization keeps using its origin's values until someone edits its own.
- No new migrations.

### Added
- **Editions.** `free` (the default) and `pro`, chosen in `config/statamic/editions.php`. Free: meta tags, Open Graph and X cards, JSON-LD, the sitemap and robots.txt, the preview with counters, redirects by hand (wildcards, 410), several sites, IndexNow and `SiteSeo` overrides. Pro adds Search Console, reports (scheduled, link checks, `seo:report`), generated share cards, automatic 301s, the 404 log, CSV import and export of redirects, and the dashboard widget. In Free, Tools → SEO shows a card for each Pro feature, linking to the Marketplace, and Pro's control panel addresses answer 404. `JothamLec\Seo\Support\Edition::pro()` tells code which it is.
- **hreflang.** With several sites, a page gets a `<link rel="alternate" hreflang>` for each language it is published in (its entry's origin and localizations, or a term on each site of its taxonomy that has entries there), itself included, with `x-default`; the sitemap carries the same as `<xhtml:link>`. Drafts, noindexed and unlisted versions are left out, and a page that is noindexed or canonical elsewhere gets none. Codes are the site's language, or its full locale (`en-GB`) where two sites share one. Config `seo.hreflang.enabled` and `seo.hreflang.x_default` (a site handle, or `false`). New `SiteSeo` methods: `alternates()`, `contentAlternates()`, `localizations()`, `hreflangCodes()`, `xDefaultSite()`, `localeAlternates()`, `contentSite()`, `sitemapSites()`.
- `og:locale:alternate` for each of a page's other languages.
- **A control panel that can be translated.** Every string is in `lang/en` (`cp.php`, `fields.php`, `reports.php`, `validation.php`, `frontend.php`), under the `seo::` namespace; publish them with `--tag=seo-translations` and copy the folder to another language. Reports are stored as keys and shown in the reader's language; older reports show as they were written.
- **Tools → SEO → Search Console** (Pro): its own screen, with the setup steps, then the connection: the key, each site's property, the last import, the pages imported, **Import now** and **Disconnect**. The overview shows the numbers once connected, otherwise a link to it.
- Search Console's check explains a key or service account Google reports as disabled, with the guide to enabling it, and the setup's first step says what to do when an organization policy blocks new keys.

### Changed
- The addon is called **Co-SEO** in the control panel and on the Marketplace. The package name, the `seo::` namespace, config and routes are unchanged.
- `og:locale`, the WebPage node's `inLanguage` and the "Page N" title suffix follow the content's own site (its language), not the site of the domain the request came in on, as the control panel preview does.
- The Search Console tab is gone from Tools → Addons → SEO; the property is set on the Search Console screen and kept in the same settings.
- "Imported 1 page." instead of "Imported 1 pages."

## 0.16.0 – 2026-10-06

Fields in a page builder's sets.

### Added
- **Fields in a page builder.** `description_fields`, `image_fields` and `faq_field` can name a field inside a Replicator's sets as `replicator.set.field`, e.g. `sections.hero.image`, with `*` for a set of any type. The description and share image come from the first visible set with one; the FAQPage has the questions of every visible set, in the page's order. Sets switched off are passed over. A plain field name means what it did.

## 0.15.0 – 2026-10-06

Statamic Pro with several sites, each on its own domain. Single sites work as before. Run `php artisan migrate` after updating: see Upgrading.

### Upgrading
- **Migrations**: four new ones add a nullable `site` column to `seo_redirects`, `seo_404s`, `seo_reports` and `seo_search_stats` (null: every site, or a row from before; nothing to backfill), and make redirects unique per site and address (`site, source`) and the 404 log per site and path (`site, path`) instead of by address alone. `php artisan migrate` runs them; they are safe on SQLite.
- **MySQL and MariaDB**: the migrations shorten `seo_redirects.source` and `seo_404s.path` to 736 characters, so the unique index on site and address stays under MySQL's 3072 bytes. On every database the longest redirect source accepted, and the longest 404 path logged, is now 736 (was 768). A longer value already stored would make the MySQL migration fail; shorten or delete it first.
- **Moving a site to several sites**: enable **Globals → SEO & brand** on each new site, with the default site as its origin (`seo:install` does it only for a set it creates, and names the sites an existing set is missing). Existing redirects, 404s, reports and Search Console numbers have no site: redirects then apply on every site, and the rest is shown on every site. Give each other site its Search Console property (Tools → SEO with that site selected, or a map in `seo.search_console.property`).
### Added
- **Several sites** (Statamic Pro). `seo:install` creates SEO & brand on every site, each other site's origin the default; a site takes what it leaves empty from its origin. An existing set must be enabled on each site by hand; the command names those it is missing.
- Redirects for one site or every site: a **Site** field on the form (shown only with more than one site), a Site column in the list and a `site` column in CSV. A site's own rule wins over one for every site from the same address; loops are looked for among the rules of each site a rule applies on. With `redirects.case_sensitive` off, the same address in another case is taken per site too (on the form, in CSV, in loop checks), and a site's own rule in another case wins over one for every site in the very case asked.
- Automatic 301s are made, repointed and removed among the moved content's own site's rules; a renamed term leaves one on each site whose address moved.
- The 404 log keeps each site's misses apart, and **Create redirect** carries the row's site.
- Reports are of one site: `php please seo:report` reports on each site in turn, or `--site=`; the schedule runs one per site; Run report in the control panel reports on the selected site. Links are checked against the report's own site's pages and redirects.
- Tools → SEO, the 404s, the reports and the dashboard widget show the site selected in the control panel.
- **Search Console per site**: one key for every site, a property per site. `seo.search_console.property` (`SEO_SEARCH_CONSOLE_PROPERTY`) is a string for every site, or a map of site handle => property; set up from Tools → SEO, it is the selected site's (the default site's in the setting it had, the others' in `search_console_properties`). `php please seo:search-console` imports each site that has one, or `--site=`; each site keeps only its own domain's pages, and the overview shows the selected site's.
- `JothamLec\Seo\Support\Sites::as($site, fn () => …)`: run code with another site as the current one.

### Changed
- The control panel preview, and the card it draws, are worked out on the content's own site (its brand values and domain), whichever domain the control panel is on. A generated card's URL is on the entry's own domain, and its label is the mount page as it is on the entry's site.
- IndexNow gets one request per domain, each naming that domain's key file.
- The longest redirect source, and the longest 404 path logged, is 736 characters (was 768); see Upgrading.

### Fixed
- The sitemap listed every site's terms, and a term counted as used by another site's entries; reports did the same. `SiteSeo::termHasEntries()` counts the current site's entries.

## 0.14.0 – 2026-10-06

The same code as 0.13.1, tagged by mistake before the several-sites work was merged; that work is 0.15.0.

## 0.13.1 – 2026-10-06

No redirects for renamed terms that have no page.

### Fixed
- Renaming a term in a taxonomy without term pages (no `{taxonomy}.show` template, so Statamic answers its addresses with a 404) no longer adds a redirect between two missing addresses, and the save dialog no longer asks about one. A site that shows such terms at an address of its own (`/tags/{slug}`) adds that redirect itself.

## 0.13.0 – 2026-10-06

Redirects that match in any letter case, and faster console boots.

### Added
- Config `redirects.case_sensitive`: set to `false`, a redirect's From matches in any letter case (`/ABOUT-US/` as `/about-us`), for a site moved off one whose addresses worked in any case (Wix, IIS). Exact and `*` sources, accented letters and other alphabets included; what a `*` matched keeps the visitor's case. Sources differing only in case then count as one address: a second one is refused, a CSV row updates the first, and a chain of redirects that would loop is caught. Default `true`: nothing changes.

### Changed
- Faster artisan commands, queue workers and test suites: the report schedule is built only for the commands that run it (`schedule:run`, `schedule:work`, `schedule:test`, `schedule:list`, `schedule:finish`). Statamic built it on every console boot, and reading the report settings cost 15–25 ms each time.

## 0.12.0 – 2026-10-06

Search Console set up from the control panel, rules per taxonomy, and fixes for gallery fields and static caching.

### Added
- **Connect Google Search Console from the control panel.** Tools → SEO lists the steps with their links, takes the service account key as an upload (kept in `storage/app/private`, never in git) and the property (the site's domain suggested; an addon setting), checks the connection, saying what to fix when Google refuses (the API not enabled, the key's email not a user of the property, no such property), and imports. Values in `.env` still win. For whoever may change the addon's settings.
- **Rules per taxonomy**: `seo.taxonomies`, like `collections`, gives term pages an `og_type`, `page_schema`, `description_fields`, `image_fields` and `faq_field`. `contentConfig()` reads a page's rules from its collection or its taxonomy.
- `SiteSeo::termHasEntries()`: one place, used by the sitemap and the reports, that decides whether a term has published entries. Override it for a taxonomy that isn't attached to the collection whose entries use it (Statamic counts none there).

### Fixed
- An image field that takes more than one file (a gallery) in `image_fields`, or a brand image field set to take several, is read: its first image is the share image. It was skipped, so the page got the generated card, and products had no picture.
- With Statamic's static caching (half measure), a 404 was cached and then answered without the addon: a redirect added for that address later never applied, and the 404 log counted one visit. 404 responses are now marked uncacheable whenever redirects or the 404 log are on.
- The sitemap is rebuilt when the Stache is cleared, as a deploy does, so rules changed in code show at once.

## 0.11.0 – 2026-10-06

Security and correctness fixes from a review, and five options no site used are gone; see Upgrading. Sites on `^0.10` change their constraint to `^0.11`.

### Removed
- Config `og.cache_store`: share cards are cached in the default store.
- Config `image.width` and `image.height`: uploaded share images are 1200×630. A site that needs another size overrides `imageWidth()` and `imageHeight()` in its `SiteSeo` subclass.
- Config `indexnow.endpoint`: addresses go to `api.indexnow.org`, which shares them with every participating engine.
- The `<s:seo:image_url />` tag.
- The per-entry **og:type** field of the SEO fieldset. A collection sets it with `og_type`; a template can still pass `og_type` to the tag. A value saved on an entry is no longer read.

### Upgrading
Remove `og.cache_store`, `image` and `indexnow.endpoint` from a published `config/seo.php` (left in, they do nothing). A template that used `<s:seo:image_url />` reads the share image from `og:image` instead, or calls `app(JothamLec\Seo\SiteSeo::class)->image($context)`. Entries that set og:type themselves take their collection's.

### Security
- The report check **Links to other sites** asked any address a page linked to, and followed redirects anywhere, so a link (or a redirect) to `localhost`, `169.254.169.254` or a private network made the server send requests into its own network. It now asks only public addresses, follows redirects itself (each checked the same way) and connects to the address it checked, so DNS can't answer differently in between.
- Redirects: a From or To address with a line break or another control character is refused (it would go into the Location header), and so is a `$1` before the path of another site's address (`https://example.com$1` let a visitor's path pick the domain).
- Report link checks no longer look at files above `public/` (`/../composer.json` counted as a working link).
- IndexNow is no longer told about a draft that is deleted: its address was never public.
- A draft parent page no longer appears, with its title and address, in its children's breadcrumbs (JSON-LD).


### Changed
- Faster CSV import of redirects: each row's loop check read every rule again, rebuilt after the row before it. It now reads only the rules that could match (300 rows: 11.6 s → 0.6 s).
- Development: Larastan (level 5) checks `src` and `routes`: `vendor/bin/phpstan`.

### Fixed
- Automatic redirects on a site whose timezone isn't UTC: an entry's saved date was read in the site's timezone, so in a collection whose route has the day (`{year}/{month}/{day}`) any save could add a redirect from a day that never existed, and a real move started from the wrong address.
- The sitemap leaves out a page whose canonical names another page of the same site, as Google asks (it already left out pages canonical to another site).
- The sitemap follows a collection or taxonomy saved with a new route, and a deploy that changes `seo.sitemap`, instead of waiting for the next content save.
- `/sitemap_{n}.xml` with a number too big for PHP answered 500; it is a 404.
- Redirects send visitors to the site's own address (Statamic's site URL), not to whatever Host header the request carried, so a forged Host can't turn a redirect into one to another domain (or poison a cache with it).
- Redirects: a rule that would come back to its own address through a chain of other rules (`/a` → `/b` → `/c` → `/a`, up to ten steps, wildcards included) is refused; only a rule leading straight back was caught.
- Search Console: a site with more than 25,000 pages in the period got only the first 25,000 (one answer's worth); the rest are now read in turns.
- The 404 log, when full, drops one-off misses first (one hit, no referrer), so a flood of made-up addresses no longer pushes out the broken links that recur or that a page links to.
- IndexNow from a queue worker: what a job changed is sent when the job is done, not when the worker stops (which could be days later).
- Reports no longer stop on a page title longer than 255 characters (MySQL in strict mode and Postgres refused it); the stored title is cut to fit.

## 0.10.0 – 2026-10-06

Run `php artisan migrate` after updating: there is a new table.

### Added
- **Google Search Console**: each page's clicks, impressions, click-through rate and average position, imported daily (`php please seo:search-console`, on the schedule) and shown on Tools → SEO with the totals and the most-clicked pages. Signed in as a service account, with no client library; off until `seo.search_console.credentials` and `.property` are set. Setup in docs/configuration.md.

## 0.9.0 – 2026-10-06

### Added
- Report check **Linked from another page**: a page in the sitemap that no other page links to is a warning (the home page is exempt).
- Report check **Links to other sites**, off by default (Tools → Addons → SEO): asks each site a page links to, several at a time, and flags only clear misses (404, 410, a host that doesn't resolve); refusals, server errors and timeouts aren't counted. Each answer is kept for a day.

## 0.8.0 – 2026-10-06

### Added
- **Products**: a collection's `product` config names its price, availability, SKU, GTIN and brand fields, and its pages get a Product with an Offer (price, currency, availability, condition, URL) and the images in three shapes. Without a price above zero or a currency there is none, as Google requires.
- **Shop tab** on SEO & brand: the currency, the return policy (a window in days, any time or none, for a country, and/or the policy's page) and shipping rates (destination, order value range, rate, days in transit). They are the publisher's `hasMerchantReturnPolicy` and `hasShippingService`, which Google recommends over per-product policies.
- `php please seo:install --tab=shop` adds the tab to an installed site.

## 0.7.0 – 2026-10-06

### Added
- **Snippet controls per page**: "No snippet" (`nosnippet`) and "Snippet length" (`max-snippet`), the controls Google documents for its results and AI Overviews and AI Mode.
- **AI crawlers in robots.txt**: two switches on SEO & brand → Crawlers. "Allow AI training" off turns away GPTBot, ClaudeBot, Google-Extended, Applebot-Extended and CCBot; "Allow AI search" off turns away OAI-SearchBot, Claude-SearchBot and PerplexityBot. Both on by default.
- **IndexNow**: published content saved, gone live or deleted is sent to IndexNow (Bing, Yandex and others; not Google) after the response, in production only, with the key served at `/{key}.txt` (`seo.indexnow`).

### Upgrading
Run `php please seo:install --fields` for the two crawler switches. IndexNow is on by default; set `seo.indexnow.enabled` to `false` to keep it off.

## 0.6.0 – 2026-10-06

Structured data checked against Google's current documentation (September 2026).

### Added
- **The publisher can be any schema.org type, or several**: a Store, an EducationalOrganization, EducationalOrganization and LocalBusiness… (typed in, or picked from common ones). New fields: other name, description, founding date, address, contact points, and for a local business coordinates and opening hours. Each property is printed only for types that accept it.
- **Other site name** (`WebSite.alternateName`), which Google's site names use.
- **`primaryImageOfPage`** on the page node: Google takes Search and Discover thumbnails from it (March 2026).
- **Article authors** from a collection's `author_field` (an entries or users field), as Persons with a name and address; the publisher remains the author otherwise.
- **Article images in three shapes**, 16:9, 4:3 and 1:1, from an uploaded image, as Google recommends.
- `php please seo:install --fields` adds the new fields to an existing SEO & brand blueprint, in the tabs it still has.

### Fixed
- A ProfilePage (`page_schema`) has the `mainEntity` Google requires: the entry, as a Person (`profileEntity()`).
- `priceRange` and `areaServed` are no longer printed for a Person, and `priceRange` only for a local business.
- A page set not to follow links keeps the default snippet and large-image robots values.
- A generated share card has alt text (its title), printed as `og:image:alt` and `twitter:image:alt`; `twitter:image:alt` is printed for uploads with alt text too.

### Changed
- The docs say that FAQPage markup is still valid but that Google no longer shows FAQ rich results (2026).

### Upgrading
Run `php please seo:install --fields` to add the new SEO & brand fields to an installed site. A publisher type saved before as one value still reads.

## 0.5.1 – 2026-10-06

### Fixed
- A site's `config/seo.php` merges into the addon's at every depth. Laravel merges an addon's config one level deep, so a site that set only `og.templates` lost `og.enabled` (and its share cards), and one that set a single `robots` key lost the others. A list a site sets still replaces the default list.

## 0.5.0 – 2026-10-06

Leaner, for fresh Statamic sites: Statamic's own settings first, and what only one site needs goes in that site's `SiteSeo` subclass.

### Removed
- **Trailing-slash redirects** (`seo.trailing_slash`, the `TrailingSlash` middleware). Leave them to the web server, or to the site. Redirect targets still get a trailing slash when the site has Statamic add them (`URL::enforceTrailingSlashes()`).
- **The "Site name" field** of SEO & brand. The site's name is Statamic's own (Settings → Sites, else `APP_NAME`); a value saved in the global is no longer read.
- **`seo.description.skip_prefixes`**. A site that must pass over some opening paragraphs overrides `contentDescription()` in its subclass.
- **humans.txt** (`seo.humans_txt`, the global's humans.txt field, `SiteSeo::humansTxt()`).
- **The per-entry "Card template" field.** A collection still picks a template with `og_template`.

### Changed
- The 404 log also leaves out what browsers and crawlers ask for on their own (`/favicon.ico`, `/apple-touch-icon*`, `/build/*`, scripts, styles, images, fonts), so broken links aren't pushed out.
- The publisher's price-range placeholder is `$$`.

### Upgrading
Remove `trailing_slash`, `humans_txt` and `description.skip_prefixes` from a published `config/seo.php` (left in, they do nothing). Set the site's name under Settings → Sites if the global's differed from `APP_NAME`.

## 0.4.0 – 2026-10-06

### Added
- **Tools → SEO** is an overview: the latest report's score, recent 404s, redirects and the brand defaults, each with a button into its screen, and the files the site serves.
- `seo:install` fills each empty "SEO & brand" field with what the site uses (site name, separator, the home page's description, the robots.txt rule), so editors see the defaults and can change them. It never overwrites a value; rerun it on a site installed before.

### Changed
- Reports list: back to opening a report from its name, now "SEO report #N"; the row no longer does.
- The control panel screens use Statamic's own components and theme colours: scores and statuses are badges, the report's "Fix" is a button, and the reds and greens follow the theme's danger and success colours. No more blue links of the addon's own.
- The title separator gets a space on each side however it is typed (`|` reads as ` | `), as the control panel can trim one.
- humans.txt is listed on the overview only when it is filled in.

## 0.3.3 – 2026-10-06

### Changed
- The X card in the search and share preview shows the title under the picture, as X does, instead of on a label over it that covered the card's own text.

## 0.3.2 – 2026-10-06

### Changed
- Tools → SEO → Reports: a finished report's whole row opens it, and its first cell reads "Report #N" as a link. Only the small "#N" was clickable.

## 0.3.1 – 2026-10-06

### Fixed
- Uploaded share images are cropped to 1200×630 again, on their focal point. The crop was asked of Glide in a form it doesn't understand, so it shrank the image to fit inside 1200×630 instead (a 2000×1125 upload came out 1120×630, a smaller one wasn't enlarged) while the meta tags said 1200×630.

## 0.3.0 – 2026-10-06

### Changed
- **Renamed** to `jotham-lec/statamic-co-seo` (GitHub `Jotham-LEC/statamic-co-seo`). Nothing else changes: the tag (`<s:seo:meta />`), the `seo::seo` fieldset, `config/seo.php`, the permissions and the addon settings file (`resources/addons/seo.yaml`) keep their names. A site that required `jotham-lec/statamic-seo` changes its repository URL and runs `composer remove jotham-lec/statamic-seo && composer require jotham-lec/statamic-co-seo`; the control panel's assets move to `public/vendor/statamic-co-seo` (the old `public/vendor/statamic-seo` can be deleted).

## 0.2.1 – 2026-10-06

### Fixed
- **Security:** the 404 log kept any `Referer` header, and showed it as a link, so a request with a `javascript:` referrer put a script one click away in the control panel. Only `http(s)` addresses are kept and linked now, including in rows already logged.
- The 404 log no longer fails on Postgres for a path or referrer that isn't valid UTF-8 (`/%C3`, `/%00`); such requests aren't logged.
- Redirects: what a `*` matched is passed on encoded (`/old/a%3Fb` went to `/new/a`; spaces and accents went into the Location header raw), and an encoded `?` in a path no longer cuts it short.
- Redirects: a From address typed or imported percent-encoded (`/caf%C3%A9`) now matches; sources are stored decoded, and ones saved before still match.
- Redirects: a To address keeps its `#fragment`, with the visitor's query string placed before it.
- Redirects: a rule back to its own address (`/a` → `/a`, `/x/*` → `/x/$1`), or to an address whose rule leads straight back, is refused, and one saved before is not served.
- Automatic redirects no longer delete a manual wildcard rule (`/shop/*` → another site) when a page moves to `/shop`; only a rule that the move would point back at itself is dropped.
- A save that was cancelled or failed no longer leaves its move behind, to be written as a redirect by the next save of that content in the same process (a queue worker, an import).
- The save dialog no longer asks when the form unpublishes the entry (no redirect is added for drafts), nor for a form opened in a stack over the page (it named the page's address); those saves add the redirect without asking.
- The sitemap now lists an entry once its scheduled date arrives (on Statamic's `EntryScheduleReached`), instead of after the next save of any content.
- Reports run by `seo:report`, the schedule or a queue worker no longer fail Blade pages that show validation errors (`@error`, `$errors`) with "Undefined variable $errors", which scored them 0.
- Two reports started at the same moment (a click and the schedule) could both run; starting is now one at a time.

### Changed
- Faster: the tag reads a page's body and works out its share image once (it did up to three times); a report's pages screen loads its entries in one query; the sitemap and reports read entries in chunks rather than all at once.

### Removed
- Config `seo.title.min` and `seo.description.min`. Nothing read them: the preview counters and the reports take their lengths from Tools → Addons → SEO.

## 0.2.0 – 2026-10-05

Run `php artisan migrate` after updating: there are new tables.

### Added
- **Search and share preview** on every page's SEO tab: the Google result, the Facebook/LinkedIn/WhatsApp card and the X card, with title and description counters, drawn from the unsaved form by the site's own rules, including the generated share card.
- **Tools → SEO**: an overview of what the site serves, with links to the brand global and the report settings.
- **Dashboard widget** `seo`: the latest report's score and the most recent 404s.
- **Permissions**: `view seo`, `manage seo redirects`, `run seo reports`.
- **Redirects** (`seo_redirects`): exact and `*` wildcard sources with `$1` captures, 301, 302 or 410, applied only to addresses that would be a 404; hit counts; CSV import and export.
- **Automatic 301s** when published content moves (slug, date, tree position, with the pages under it; a wildcard rule for a moved mount page), without chains or loops. Saving a page whose address changes asks whether to add one.
- **404 log** (`seo_404s`): one row per path, capped, without bots or scanner probes, with a "Create redirect" action.
- **Reports** (`seo_reports`, `seo_report_pages`): every published page rendered inside the app and checked against eleven rules, scored per page and for the site; `php please seo:report`, a CP button, an optional schedule; settings under Tools → Addons → SEO.
- Documentation in `docs/`.

### Changed
- The preview's description target is 50–160 characters (it was 155), and both length targets come from the report settings.

### Fixed
- The SEO title's help text no longer contains a raw `<title>` tag, which the control panel rendered as the browser tab's title.

## 0.1.0 – 2026-10-05

First release.

- `<s:seo:meta />`: title, description, robots, canonical, Open Graph and X tags, verification codes, and one JSON-LD `@graph` (WebSite, publisher, WebPage, BreadcrumbList, Article, FAQPage, custom nodes).
- The `seo::seo` fieldset and the "SEO & brand" global set (`php please seo:install`).
- `/sitemap.xml` (with an index above 1,000 URLs), `/robots.txt`, `/humans.txt`.
- Generated share cards at `/og.png` and `/og/{uri}.png` with simonhamp/the-og, cached and cookie-free, with per-page text and template overrides and custom templates.
- Trailing-slash redirects (`seo.trailing_slash`).
- One extension point for per-site rules: a subclass of `JothamLec\Seo\SiteSeo`.
