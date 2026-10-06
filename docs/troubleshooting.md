# Troubleshooting

### I see "Pro" cards instead of reports, 404s or Search Console

The addon is running as Free. If you bought Pro, set it in `config/statamic/editions.php`: `'addons' => ['jotham-lec/statamic-marketing-toolkit' => 'pro']`, then clear the config cache (`php artisan config:clear`) if the site caches it. Statamic's own `'pro' => true` in the same file is Statamic CMS Pro, not this addon's edition.

### No hreflang tags, or a language is missing from them

- hreflang needs several sites (Statamic Pro, `multisite` on), Marketing Toolkit Pro and `seo.hreflang.enabled`.
- The pages must be linked: a translation is an entry **localized** from another (it has an `origin`), not a separate entry with the same title.
- A version that is a draft, noindexed, left out of the sitemap or canonical elsewhere is left out. If the page you're looking at is one of those, it gets no tags at all.
- Page 2 and later of a listing, and error pages, get none.

### Search Console: I can't create a key, or Google says the key is disabled

New Google Cloud projects often block service account keys with the organization policy `iam.disableServiceAccountKeyCreation`. Someone who administers the organization can allow keys for the project in [Organization policies](https://console.cloud.google.com/iam-admin/orgpolicies/iam-disableServiceAccountKeyCreation), then you create the key as usual. A key (or service account) that exists but is disabled can be [enabled again](https://docs.cloud.google.com/iam/docs/keys-disable-enable). **Check the connection** on Tools → SEO → Search Console says which of these Google reports.

### Share cards: "Imagick PHP extension must be installed"

the-og draws cards with Imagick. Install PHP's `imagick` extension (on NixOS, add `all.imagick` to the PHP `buildEnv` extensions). Uploaded share images don't need it; only generated cards do.

### The SEO screens are unstyled or blank

The built assets weren't published. Run `php artisan vendor:publish --tag=marketing-toolkit --force`, then reload without cache. Statamic republishes them on `composer update`.

### No automatic redirect when a slug changes

- Is the cache store `array`? Automatic redirects compare the entry with the copy loaded before it was edited, and the `array` store returns the same object, so nothing looks changed. Use `file`, `redis` or `database`.
- Only **published** entries get one: a draft has no public address to protect.
- `seo.redirects.automatic` and `seo.redirects.enabled` must be `true`, and the addon must be Pro: Free adds no automatic redirects.
- Did someone answer "Don't add" in the save dialog? That skips it for that save.

### A redirect doesn't apply

Redirects only apply to addresses that would be a 404. If a page exists at the source address, the page wins. Also check that the redirect is **Active**, and that its source has no query string (`?…`): addresses are matched without one. With Statamic's static caching on, a 404 cached before this version keeps being served until the static cache is cleared (`php please static:clear`); 404s aren't cached any more.

### Nothing appears in the 404 log

Requests from bots and tools are left out on purpose, including `curl` and `wget`; try a browser. Paths like `*.php` and `/wp-*` are ignored too (`seo.not_found.ignore_paths`). The log only sees requests that reach Statamic: a 404 answered by the web server (a missing file under `/build`, for example) never gets there.

### Every page says noindex

That's `seo.robots.noindex_outside_production`: unless `APP_ENV=production`, every page is noindexed so test copies stay out of search results. Reports ignore it while they run, so a report on a local copy still means something.

### A report stays at 0 pages, or a step times out

- **With a queue worker** (`QUEUE_CONNECTION` other than `sync`), is the worker running? The CP queues one job per step.
- **Without one**, the report advances while its screen is open, one step per progress request. Keep the tab open, or run `php please seo:report` in a terminal.
- If a step times out, lower **Pages per step** (Tools → Addons → SEO → Running).
- A report that stops moving for 30 minutes is marked failed when the next one starts.

### A report flags links that work

The link check looks the address up without fetching it: content Statamic knows, a file in `public/`, an asset, a route the app registers, or a redirect. A link handled some other way (a web-server rewrite, another app on the same domain) shows as broken. Links to files under a shared folder that isn't on your local copy (uploads kept on the server only) also show as broken locally but not in production.

### I can't log in to the control panel locally

- `SESSION_DOMAIN` in `.env` must match the address you use (or be empty). After changing `.env`, restart `php artisan serve`; it reads `.env` only at startup.
- Statamic Core allows **one** user. With a copy of a production database, log in as that user instead of creating another.

### Tests: "Statamic matches the site…" or every page 404s

Request front-end pages with the site's full address (`https://example.test/about`), not `/about`: Statamic finds the site by its absolute URL, and the test client uses `localhost`.
