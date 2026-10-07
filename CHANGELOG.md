# Changelog

## 0.22.0 – 2026-10-07

Signed-in editors get a toolbar on the live site, showing what the addon knows about the page in front of them and where to fix it.

### Added
- **A front-end toolbar for anyone who may use the control panel.** A button in the bottom-left corner shows the page's SEO score; it opens a bar with **Edit entry** and **SEO** (the edit screen on the tab that holds the SEO fields), and panels for the page's score and failing checks, its Google result and share card, redirects to it and its 404s, the tracking tags and Consent Mode, the page on other sites, and **Refresh this page's cache**. Each panel follows the permission its control panel screen asks. It works by keyboard (Escape, and **Alt+Shift+M** to open and close it), at 320 px as a bottom sheet, and in the user's control panel theme. It never shows in Live Preview or a frame. See [editors.md](docs/editors.md#the-front-end-toolbar).
- **Three user preferences** under Preferences → Marketing Toolkit: hide the toolbar on every device, put it in the bottom-right corner, and change or clear its shortcut.
- **It costs visitors nothing, and pages stay safe to cache.** `<s:mt:body />` ends with a script of about 300 bytes, the same for everyone, which loads the toolbar (about 6 kB gzipped) only when the `mt_toolbar` cookie says a control panel user is signed in. Everything personal comes from an uncached endpoint, `/!/marketing-toolkit/toolbar`, that checks the session. A layout without `mt:body` adds `<s:mt:toolbar />` before `</body>`. See [configuration.md](docs/configuration.md#toolbar).

### Fixed
- **The SEO tab's Sharing and Advanced headings show their names**, not the translation keys `marketing-toolkit::fields.seo.sharing.display` and `…advanced.display`, which they showed since 0.21.0.

### Upgrading
- **The toolbar is on after the update.** To switch it off for everyone, turn off **Front-end toolbar** under Marketing → Settings → Features (`composer update` adds the switch to the tab), or set `'toolbar' => ['enabled' => false]` in `config/marketing-toolkit.php`.
- **Run `php artisan migrate`**: it adds an index to the report pages table for the toolbar's lookup.
- **If the toolbar doesn't appear** while you're signed in, publish the addon's assets again, `php artisan vendor:publish --tag=marketing-toolkit --force`, so `public/vendor/statamic-marketing-toolkit/build/toolbar.js` is there, then reload a control panel page once to get the cookie.
- **A control panel on another domain than the site** needs `SESSION_DOMAIN` set to their shared parent (`.example.com`) for the toolbar to see the sign-in.

## 0.21.4 – 2026-10-07

### Fixed
- **Updates no longer rewrite your templates.** `RenameFromSeo` ran on every update and turned every `{{ seo:` and `<s:seo:` in `resources/views` into `mt:`, including the Brand global's values (`{{ seo:site_name }}` became `{{ mt:site_name }}`, which shows nothing) and a `seo` tag of the site's own. Undoing it didn't help: the next update did it again. It now runs only when updating from 0.19 or earlier, or on a site that still has one of the old names it renames (an `import: seo::seo`, the addon's `config/seo.php`, a role's old permission). It renames only the addon's four old tags (`seo:head`, `seo:body`, `seo:meta`, `seo:favicons`, in Antlers and Blade), and leaves the templates alone on a site with a `seo` tag of its own. Another package's `config/seo.php` or `lang/vendor/seo` is no longer moved. If an update renamed your own `seo:` values, change them back once: they stay that way now.
- **A field you removed from Brand or Marketing settings stays removed.** `AddNewBrandFields` added back every addon field the blueprints lacked, on every update. It now adds only the fields the versions you're updating across brought (from 0.19 or earlier, every missing field, as before).
- **A field you put back in Brand after 0.21 stays there.** `MoveToMarketingSettings` now runs only when updating from 0.20 or earlier, or with `updates:run 0.20.0`.

### Upgrading
- Coming from Co-SEO, run `php please updates:run 0.17.0 --package=jotham-lec/statamic-marketing-toolkit` (it was `0.19.0`), so the update scripts for 0.18 run too: see [upgrading.md](docs/upgrading.md#from-co-seo).

## 0.21.3 – 2026-10-07

### Fixed
- **The overview's sentences agree with their numbers.** The Redirects card said "1 of them were added" when one redirect was automatic, and the warning about tags beside Google Tag Manager said "so are Google Analytics 4" for one tool and joined several without "and". Both now read correctly for one or several.

### Changed
- **The README works as the Statamic Marketplace listing.** It reads as full sentences, has an Installation section, and uses full addresses for every link and image. The badges are gone, because the Marketplace stacks them one per line.
- New screenshots in the README and docs show the overview, a report and the pages it flagged, an entry's search and share preview, redirects, the tracking warning, and the Features tab.

## 0.21.2 – 2026-10-07

### Fixed
- **The old field descriptions are taken out on a site coming from 0.19 or earlier.** `DropFieldDescriptions` looked for them under their `marketing-toolkit::` names only, but on such a site they are still `seo::` when it runs (Statamic decides which scripts run before any of them does, so `RenameFromSeo` renames them afterwards), and Brand's fields showed raw keys such as `marketing-toolkit::fields.brand.title_separator.instructions` underneath. It now takes out both. A site that updated to 0.21.0 or 0.21.1 has them taken out on its next `composer update`: see [upgrading.md](docs/upgrading.md#from-020-brand-and-marketing-settings).

## 0.21.1 – 2026-10-07

### Fixed
- **Overview is highlighted on the overview only.** Its address starts every other Marketing screen's, so it stayed highlighted beside Reports, Redirects and the rest.
- The screenshots in the README and docs show the 0.21 control panel.

## 0.21.0 – 2026-10-07

The control panel gets its own Marketing section, the brand is kept apart from the tracking and crawler settings, and reports can be exported.

### Changed
- **The control panel has a Marketing section** in the sidebar, between Fields and Tools, instead of Tools → SEO. Its items are Overview, Reports, Redirects, 404s, Search Console, Brand and Settings.
- **"SEO & brand" is split in two.** **Brand** (handle `seo`, `marketing-toolkit.global`) keeps the Brand, Publisher, Shop and Share cards tabs. The new **Marketing settings** global set (handle `marketing`, `marketing-toolkit.settings_global`), shown in the nav as Settings, has the Tracking, Consent, Leads, Crawlers and Features tabs. Features replaces the separate Features screen; it shows on the default site only, and its switches are copied to the addon settings when it is saved. Each site has its own values in both. `php please mt:install` creates both sets.
- **The report settings are on the Reports screen.** Marketing → Reports has a Reports tab and a Settings tab, which holds the checks, the length limits, the pages and the schedule that used to be under Tools → Addons. The Settings tab is shown to people who may change the addon's settings.
- **The SEO fields on each entry are grouped.** Under the title and description, a Sharing heading has the share image, card title and card subtitle, and an Advanced heading has the canonical URL, hide from search engines, do not follow links, in sitemap, no snippet, snippet length and extra JSON-LD. Snippet length is hidden while No snippet is on.
- The control panel's text has been rewritten in full sentences, and fields no longer carry descriptions: [editors.md](docs/editors.md) explains each one.

### Added
- **A checklist for moving an existing site** to Marketing Toolkit ([docs/migrating.md](docs/migrating.md)), and a [skill for AI coding agents](docs/agent-skill/README.md) that follows it.
- **Export CSV on each report**: one row per page, the worst score first, with the columns Address, Title, Score, Failed checks and Warnings.

### Upgrading
- **`composer update` moves your settings to Marketing settings.** The `MoveToMarketingSettings` update script creates Marketing settings on the sites Brand is on, moves each site's tracking, Consent Mode, leads and crawler values across, removes those fields from Brand's blueprint (fields the site added itself stay), and renames "SEO & brand" to "Brand". Until it runs, the values are still read from Brand. Commit the blueprints and global sets it changes, give the roles that need it access to the new set, and point any template that reads those fields from the `seo` global at `marketing`. See [upgrading.md](docs/upgrading.md#from-020-brand-and-marketing-settings).

## 0.20.0 – 2026-10-07

From a developer-experience audit: one set of names, a fresh install that works the first time, cached routes that follow the Features switches, a faster `<head>`, and one way to do each thing.

### Upgrading
- **Free and open source.** Marketing Toolkit is now MIT-licensed with no editions: everything that was Pro (several sites and hreflang, Consent Mode, leads, campaign links, Search Console, reports, share cards, automatic 301s, the 404 log, redirect CSV, Features and the widget) is in every install. Remove the addon's line from `config/statamic/editions.php` if you added one.
- **The `seo` names are now `marketing-toolkit` and `mt`.** `config/marketing-toolkit.php`, the `marketing-toolkit::` fieldset, views and translations, `/cp/marketing-toolkit`, the permissions `view marketing toolkit`, `manage marketing toolkit redirects` and `run marketing toolkit reports`; the tags `<s:mt:head />`, `<s:mt:body />`, `<s:mt:meta />` (`{{ mt:head }}`…), the commands `mt:install`, `mt:report` and `mt:search-console`, the tables `mt_*`, route names `mt.*`, `MT_*` in `.env` and the widget `'type' => 'mt'`. **`composer update` renames them in the site's files** (blueprint imports, templates, the widget, `config/seo.php`, published translations, roles' permissions): commit what it lists. **`php artisan migrate` renames the tables** and moves an uploaded Search Console key. The `SEO_*` `.env` names are read until 1.0. What to check by hand: [upgrading.md](docs/upgrading.md#from-019-the-seo-names). The entries' `seo` fields and the `seo` global set keep their names: they are content.
- **Put `<s:mt:head />` after `<meta charset>` and the viewport**, not first in the `<head>`. Browsers look for the charset in the first 1024 bytes, and with tracking tags it ended up several thousand bytes in.
- **Delete `public/robots.txt` and `public/favicon.ico`** if they came with Statamic: the web server answered with them, so the addon's robots.txt (with its `Sitemap:` line and the control panel rule) and icons never showed. `php please mt:install` names such files and offers to delete them.
- **`seo:install --fields` is gone.** The fields a new version brings are added to SEO & brand by an update script on `composer update`, and by any rerun of `mt:install`. Commit the blueprint it changes.
- **Override `SiteSeo` by binding your subclass** in a service provider, `$this->app->bind(SiteSeo::class, App\Seo::class)`, as for `Tracking`. `'class'` in the config file still works until 1.0, and is no longer in the published file.
- Config keys: `robots_txt`, `llms_txt` and `ads_txt` are now `robots_txt.enabled` and so on, like every other switch; `tracking.gtm`, `ga4`, `meta_pixel` and `linkedin` are now `gtm_id`, `ga4_id`, `meta_pixel_id` and `linkedin_partner_id`, each the field's handle and `MT_` + the key in `.env`. A file published with the old keys keeps working.
- **Generated descriptions are cut at 160 characters** (`marketing-toolkit.description.length`, was 155), the report's own limit, so a generated description passes its check. The SEO fieldset's title and description no longer have a character limit of their own: the preview's counters, with the report's limits, are the guide.

### Fixed
- **Cached routes follow the Features switches.** A module off when `php artisan route:cache` (or `optimize`) ran kept answering 404 after it was switched back on. The sitemap, robots.txt, llms.txt, ads.txt, IndexNow key, icon and share-card routes are always registered; each answers 404 while its module is off.
- **The meta tags take about a quarter of the time** on every page that isn't statically cached (75 ms → 18 ms on a test machine): the logo, default image, icon and card picture in SEO & brand are found from their stored path, instead of building every field of the set to read them.
- `mt:install` refuses an asset container or a tab that doesn't exist, naming the ones that do; a rerun with nothing to add says so; filled-in defaults are named by their labels.

### Changed
- `mt:install` offers to enable an existing SEO & brand on the sites it's missing from.
- Tools → SEO says which tracking ID is set but isn't an ID (and so isn't printed), and where it is set; an ID from a `Tracking` subclass is no longer labelled "set in .env"; the GTM warning says to clear IDs wherever they are set.
- Tools → SEO marks a file in `public/` that is served instead of the addon's.
- In Free, a Pro screen shows what Pro adds instead of a 404.
- The daily Search Console import is in the schedule (and `schedule:list`) only once Search Console is set up.

### Developers
- The `SiteSeo` methods docs/developers.md lists are marked `@api`: their names and signatures change only in a major version. The others are internal.
- Changes to sites' content and settings are update scripts (`src/UpdateScripts`), not migrations: the 0.18.3 title-toggle migration is now the `KeepSiteNameInTitles` update script, so `php artisan migrate` on a server no longer writes content files.
- docs/developers.md has a table of the addon's names and the rule behind them. Statamic now names the fieldset, blueprints and translations after the slug itself; the overrides that kept `seo` are gone. Working on the addon moved to CONTRIBUTING.md, with how to run one test.
- `phpunit.xml` sets `APP_ENV=production`, as the tests always ran; CI runs `composer validate --strict`. Each test process, serial runs included, starts from a fresh copy of Testbench's skeleton, so files an earlier run left (in `vendor/` too) can't fail a test.

## 0.19.0 – 2026-10-07

Fixes from a second audit, a licence, and the requirements stated and tested.

### Upgrading
- **Statamic 6.34 or later.** Earlier 6.x releases fail parts of the addon (addon settings, Features, lead tracking), and 6.30 and earlier carry a security advisory (CVE-2026-71293) that Composer won't install.
- **PHP's `curl`, `dom`, `mbstring` and `openssl` extensions are required** in `composer.json`; almost every PHP build has them. `imagick` (share cards, favicons) or `gd` (favicons) are suggested. See the README's Requirements.
- **`seo.tracking.class` is gone.** A site that set it binds its subclass in its own service provider instead: `$this->app->bind(Tracking::class, MyTracking::class)` (see the developers' docs).
- A Search Console key uploaded in the control panel is now stored encrypted. Keys saved before keep working; after a change of `APP_KEY`, upload the key again.

### Security
- **Redirects keep to the sites a user may work on.** With several sites, someone who may manage redirects but only on some sites can no longer list, export, edit, import or delete another site's rules; rules for all sites stay theirs to manage.
- Search Console shows people who may only view SEO whether each site is connected, not its property, and lists only the sites they may access.
- A new Search Console key for the same account signs in afresh instead of reusing the old key's token, and an empty token is never kept.

### Fixed
- **Saving a redirect no longer rewrites its target's query.** Every save of a campaign-capable form rebuilt the query string: `?q=$1` became `?q=%241` (the wildcard stopped filling in), `a.b` became `a_b`, `+` became a space, a bare `?flag` gained `=`. Only the `utm_*` tags are touched now, and only when they change.
- **A CSV cell in quotes may hold a line break**; it split the row in two. Errors now name the row rather than the line.
- Importing a large CSV checks for redirect loops against rules read once, not the whole table per row.
- **Sites that share a domain keep their own Search Console numbers** (`example.com/` and `example.com/fr/`): each page counts for the site whose address it starts with.
- The CSV export lists each address's all-sites rule first on Postgres too.
- A report runs one step at a time, however many tabs or workers ask, and a second click on **Run** while it starts queues no second run.
- The 404 log keeps a one-off address only when one of the site's own pages links to it; a made-up `Referer` header no longer protects it.
- Answering the "add a redirect?" question twice (a double click) counts the first answer only.
- The control panel preview could say a generated share card wasn't generated when the second ticked over between two reads.
- **"Don't save yet" on the redirect question says "Not saved."**, not "Something went wrong": the save now stops the way Statamic expects.
- The Search Console steps have a space between each step's title and its text.

### Changed
- `LICENSE.md` sets out the terms: Free on any number of sites, Pro per production site for a major version (a 0.x licence also covers 1.x), with the third-party software it relies on.
- The docs list every request the addon sends out, what goes with it and when (configuration → What it sends where).
- The Composer package leaves out the tests, docs and build files.
- Errors behind a silent fallback (edition, Features, Search Console, favicons) are reported to the log; a failed Search Console check shows a plain message, not the raw exception.

### Developers
- `SiteSeo` is split into traits by area (`src/Concerns`); every method keeps its name, visibility and signature, so subclasses work unchanged. New overridable `hiddenOutsideProduction()`.
- The SEO & brand blueprint `seo:install` writes lives in `resources/install/seo.yaml`.
- `composer test` runs the suite in parallel (about a minute); GitHub Actions runs it on PHP 8.3 with Statamic 6.34, PHP 8.4 with the latest, on Postgres and without Imagick, plus Pint and PHPStan.
- Dead code out: duplicate registrations Statamic autoloads, thirteen identical rule labels, unused methods and constants, config fallbacks the merged config always has.

## 0.18.4 – 2026-10-06

Fixes from an audit: access, speed, accessibility and a leaner control panel.

### Security
- A report from another site answers 404 on several sites, and only a user who may run reports moves a running report on.

### Fixed
- **Free on several sites no longer prints favicon links on the other sites' domains**, where the icon files answer 404.
- Importing a large CSV of redirects reads the table once instead of once per row, and clears the cached rules once, after the rows are saved.
- The report's checks can be chosen from the keyboard; tables scroll on small screens; the Search Console fields have labels; progress and results are announced to screen readers.
- Escape or a click outside the "add a redirect?" question no longer means "don't add": it leaves the entry unsaved, with three explicit buttons.

### Changed
- The free overview shows one "What Pro adds" card, and on several sites a warning of what the free edition leaves out (also in the README and docs).
- A user without the permission for an SEO screen is sent back with Statamic's usual message instead of a bare 403.
- The 404 log trims itself now and then rather than on every new address, so it can run about a tenth over `max_rows`.
- `seo.leads.enabled` is in `config/seo.php`; dates follow the user's control panel locale; the Features intro sits above the switches.

## 0.18.3 – 2026-10-06

### Fixed
- **A site with a title separator saved keeps the site name in its titles after saving SEO & brand.** Since 0.18.2 the new **Add the site name to page titles** toggle showed off there, and the next save dropped the site name from every title. A migration turns the toggle on wherever a separator is saved and the toggle isn't; run `php artisan migrate`.
- `/ads.txt` with nothing to serve, a sitemap page past the last, a missing favicon, robots.txt, llms.txt or share card answer with the site's own 404 page, like any missing page. They used Laravel's bare 404, which renders the 404 view without Statamic's cascade: a 404 page that reads `$site` or a global failed, and the visitor got an error page instead.

## 0.18.2 – 2026-10-06

### Changed
- **Page titles no longer end with the site name by default**: "Pricing", not "Pricing · Your site", as most top Google results are. A new toggle in SEO & brand, **Add the site name to page titles**, turns it back on, with the separator beside it. Sites that already have a separator saved keep their titles until the toggle is changed; run `php please seo:install --fields` to add the toggle to an existing blueprint.

## 0.18.1 – 2026-10-06

Two fixes from Co-SEO 0.13.2 and 0.13.3 that 0.18.0 left out, two security fixes, and a new price.

### Changed
- **Pro is $39 per site.** A licence covers every release of one major version; one bought during 0.x also covers 1.x.

### Security
- The Search Console setup screen printed the uploaded key's email as HTML, so a crafted key could run a script for other admins. The email must now be an email address, and the screen escapes it.
- The Redirects and 404s action endpoint ran any action registered on the site, if that action didn't check permissions itself. It now runs only the addon's own actions (Delete, Create redirect).

### Fixed
- With Runway installed, the Redirects and 404s listings answered 500: Statamic asks every registered action whether it applies to a row, and Runway's Publish and Unpublish assume any database row is one of theirs. The listings now offer only the addon's own actions (Delete, Create redirect).
- Between installing or upgrading the addon and running `migrate`, a missing page answered 500: the redirect lookup read a table that wasn't there yet. The lookup, the hit count and the 404 log are now reported when they fail, and the page answers its 404 as it would without them.

### Docs
- A site that prints its own meta tags puts the tracking tags in its layout with `Tracking::head()` and `<s:seo:body />`, not `<s:seo:head />`.

## 0.18.0 – 2026-10-06

Co-SEO is now **Marketing Toolkit**: the marketing fundamentals of a website, not only its SEO.

### Upgrading
- **Pro is $75 per site**, a perpetual licence, on the [Marketplace](https://statamic.com/addons/jothamlec/marketing-toolkit).
- **New package name.** `composer remove jotham-lec/statamic-co-seo --no-update && composer require jotham-lec/statamic-marketing-toolkit`, then rename the key in `config/statamic/editions.php` to `jotham-lec/statamic-marketing-toolkit`. Everything else stays: see [Upgrading from Co-SEO](docs/upgrading.md).
- PHP classes moved from `JothamLec\Seo` to `JothamLec\MarketingToolkit`.
- For tracking: swap `<s:seo:meta />` for `<s:seo:head />` at the top of the `<head>`, add `<s:seo:body />` after `<body>`, remove the site's own tracking snippets, and run `php please seo:install --fields --tab=tracking` (it also adds the icon fields).

### Added
- **Tracking.** Google Tag Manager, Google Analytics 4, PostHog, the Meta Pixel and the LinkedIn Insight Tag, from a new **Tracking** tab of SEO & brand (`php please seo:install --tab=tracking` on an existing site) or `config/seo.php` / `.env` (`SEO_GTM_ID`, `SEO_GA4_ID`, `SEO_POSTHOG_KEY`, `SEO_POSTHOG_HOST`, `SEO_META_PIXEL_ID`, `SEO_LINKEDIN_PARTNER_ID`), which win. In production only, never in Live Preview; IDs that don't look like IDs are never printed; Vite's CSP nonce is added. New tags `<s:seo:head />` (consent defaults and tags first, then the meta tags) and `<s:seo:body />` (the `<noscript>` fallbacks); `<s:seo:meta />` is unchanged. With GTM and another tool both set, the Tracking tab, the save and Tools → SEO warn that each visit may count twice.
- **Consent Mode v2** *(Pro)* for an existing cookie banner: the four signals' defaults, `wait_for_update`, and the regions where they apply (with an "EEA, UK and Switzerland" choice). A consent bridge holds back Meta, LinkedIn and PostHog loaded directly until the banner grants consent. See [docs/tracking.md](docs/tracking.md).

- **Leads** *(Pro)*: a Statamic form submission is sent to each tool as a lead (`generate_lead` for GTM and GA4, `Lead` for Meta, `form submitted` for PostHog, a LinkedIn conversion ID), through a short-lived `mt_conversion` cookie that works with static caching; `window.mtConversion(form)` for other forms. **Lead source** (off until turned on): the first visit's UTM tags, referrer and landing page, kept in an `mt_source` cookie (after consent, with Consent Mode) and saved with each submission in fields `php please seo:install --forms` adds to every form.
- **Campaign links** *(Pro)*: a redirect's form has UTM fields (source, medium, campaign, content, term) that are added to its target's query string, so a short address like `/go/linkedin` carries the campaign; the redirect counts the clicks.
- **/llms.txt**: the site's name and description, then each sitemap collection's pages as Markdown links with their descriptions (`seo.llms_txt`, `SiteSeo::llmsTxt()`), cached until content changes. **/ads.txt** from a new **ads.txt** field in SEO & brand → Crawlers (`seo.ads_txt`). Both Free; a file in `public/` wins.
- **Features** *(Pro)*: Tools → SEO → Features switches modules off (sitemap, robots.txt, llms.txt, hreflang, IndexNow, share cards, redirects, automatic redirects, the 404 log, scheduled reports, tracking, leads, favicons, ads.txt). A module that's off is off in the config before anything registers: its routes answer 404 and its listeners and middleware aren't loaded. New config keys `seo.tracking.enabled` and `seo.leads.enabled`.
- **The SEO score as a gauge** (Pro): the overview, a report's screen and the dashboard widget show the score out of 100 on a red-to-green gauge, with the checks most pages fail beside it on the overview.
- **Favicons.** From one image in SEO & brand (new **Icon**, **Theme colour** and **Icon background** fields; `php please seo:install --fields`): `/favicon.ico`, `/favicon.svg`, `/apple-touch-icon.png`, `/icon-192.png`, `/icon-512.png` and `/site.webmanifest`, with their `<link>` tags and `theme-color` in `<s:seo:head />` (or `<s:seo:favicons />`). Made with Imagick or GD, cached, made again on save, served without a session. A file in `public/` wins. `seo.favicons.enabled`.

### Changed
- **Several sites and hreflang are Pro.** On a multi-site install, Free looks after the default site: pages on every site keep their meta tags, but there is no hreflang or `og:locale:alternate`, the sitemap lists the default site alone, other domains answer 404 for `sitemap.xml` and `robots.txt`, IndexNow sends the default domain's pages only, and Tools → SEO shows the default site with a card for Pro. Multi-site needs Statamic Pro anyway; set the addon to Pro to keep everything.
- The addon's slug is `marketing-toolkit`: its scripts are published to `public/vendor/statamic-marketing-toolkit` (tag `marketing-toolkit`), and its settings are kept under the new name: a migration copies Co-SEO's (`resources/addons/seo.yaml`, or the `addon_settings` row with the Eloquent driver). `config/seo.php`, the `seo::` views, translations and fieldsets, routes, permissions and tags keep their names.

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

## 0.13.3 – 2026-10-06

The redirects and 404 listings work beside Runway. Released from a branch off 0.13.2 for sites on 0.13; the fix reached the main line in 0.18.1.

### Fixed
- With Runway installed, the Redirects and 404s listings answered 500: Statamic asks every registered action whether it applies to a row, and Runway's Publish and Unpublish assume any database row is one of theirs. The listings now offer only the addon's own actions (Delete, Create redirect).

## 0.13.2 – 2026-10-06

A missing page answers 404 even before `migrate`. Released from a branch off 0.13.1 for sites on 0.13; the fix reached the main line in 0.18.1.

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
