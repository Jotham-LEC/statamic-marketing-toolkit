# Changelog

## 0.13.3 – 2026-10-06

The redirects and 404 listings work beside Runway.

### Fixed
- With Runway installed, the Redirects and 404s listings answered 500: Statamic asks every registered action whether it applies to a row, and Runway's Publish and Unpublish assume any database row is one of theirs. The listings now offer only the addon's own actions (Delete, Create redirect).

## 0.13.2 – 2026-10-06

A missing page answers 404 even before `migrate`.

### Fixed
- Between installing or upgrading the addon and running `migrate`, a missing page answered 500: the redirect lookup read a table that wasn't there yet. The lookup, the hit count and the 404 log are now reported when they fail, and the page answers its 404 as it would without them.

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
