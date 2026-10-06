# Changelog

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
