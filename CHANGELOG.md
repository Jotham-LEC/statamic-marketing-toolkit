# Changelog

## 0.2.1 – unreleased

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
