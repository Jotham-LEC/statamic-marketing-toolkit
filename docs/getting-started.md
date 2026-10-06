# Getting started

From nothing to a site with meta tags, a sitemap, share cards, redirects and reports. Allow about twenty minutes.

## What you need

- **Statamic 6** (Core is enough) on **PHP 8.3+**. Several sites and languages need Statamic Pro, as Statamic itself does, and Marketing Toolkit Pro; see [Several sites and languages](developers.md#several-sites-and-languages).
- **PHP's `imagick` extension** for the generated share cards (Marketing Toolkit Pro). Without it the cards (and their tests) fail; everything else works.
- **A database** Laravel can migrate. Redirects, the 404 log and reports live in tables, even on a flat-file site; SQLite is fine.
- **A cache store that serializes**: `file`, `redis`, `database` or `memcached`. Not `array`: automatic redirects need to compare an entry with the copy loaded before it was edited, and the `array` store hands back the same object.
- **For Pro, a licence** for the live site, from the [Statamic Marketplace](https://statamic.com/addons/jothamlec/marketing-toolkit). Local and staging sites don't need one.

## The editions

Marketing Toolkit comes as **Free** and **Pro**. Free is what a site gets after installing: meta tags, Open Graph and X cards, JSON-LD, the sitemap and robots.txt, the preview with its counters, redirects by hand (wildcards, 410s), IndexNow, and `SiteSeo` overrides. Pro adds Google Search Console, reports (on a schedule, with link checks and `seo:report`), generated share cards, automatic 301s, the 404 log, CSV import and export of redirects, several sites and languages with hreflang, and the dashboard widget.

To run Pro, buy it on the Marketplace and set it in `config/statamic/editions.php`:

```php
'addons' => [
    'jotham-lec/statamic-marketing-toolkit' => 'pro',
],
```

In Free, Pro's screens aren't there and Tools → SEO shows a card for each of them instead. The tables Pro fills (reports, the 404 log, Search Console's numbers) stay in the database either way, so switching back and forth loses nothing.

## 1. Install the package

```bash
composer require jotham-lec/statamic-marketing-toolkit
php artisan migrate                          # seo_redirects, seo_404s, seo_reports, seo_report_pages, seo_search_stats
php please seo:install                       # the "SEO & brand" global set
php artisan vendor:publish --tag=seo-config  # optional: config/seo.php, to change the defaults
```

`seo:install` uses the first asset container for the logo and default image; pass `--container=handle` to choose another. It also fills the empty brand fields with what the site already uses (`·` as the separator, the home page's description, the control panel kept out of robots.txt), so they show in the control panel ready to change. Running it again overwrites nothing. With several sites it creates the set on each, the others taking what they leave empty from the default site; a set that already exists must be enabled on each site by hand (the command names the sites it is missing).

The control panel's scripts and styles are published to `public/vendor/statamic-marketing-toolkit` when Composer installs or updates the package. If the SEO screens look unstyled, publish them yourself: `php artisan vendor:publish --tag=marketing-toolkit --force`.

## 2. Add the SEO fields to your blueprints

In each blueprint whose pages should have SEO fields (usually every collection with a route), add the fieldset where you want it, typically on its own tab:

```yaml
tabs:
  seo:
    display: SEO
    sections:
      -
        fields:
          -
            import: seo::seo
```

This adds the **search and share preview** and one `seo` group: title, description, share image, card title and subtitle, canonical URL, noindex, nofollow, no snippet, snippet length, "in sitemap" and extra JSON-LD. Every field is optional; empty means "use the default". See [What each value falls back to](developers.md#what-each-value-falls-back-to). On a site in several languages, each language has its own values.

Already have your own `seo` group with `title`, `description` and `canonical`? Those keys are read as they are, so swapping your fieldset for `seo::seo` keeps existing data.

## 3. Print the tags in your layout

In the layout's `<head>`, remove your own `<title>`, description, canonical, Open Graph and JSON-LD tags, and add:

```blade
<s:seo:meta />
```

or in Antlers:

```antlers
{{ seo:meta }}
```

Pages that aren't Statamic entries (a controller page, a 404 view) pass what they know:

```blade
<s:seo:meta title="Contact" description="Write to us." />
<s:seo:meta :canonical="false" status="404" />
```

All the parameters are in [developers.md](developers.md#the-tag).

## 4. Fill in "SEO & brand"

In the control panel, open **Globals → SEO & brand**: separator, default description and share image, who publishes the site (organisation, local business or person), verification codes, robots.txt lines, humans.txt and the share-card colours. [editors.md](editors.md#seo--brand) explains each field.

## 5. Add the dashboard widget (optional, Pro)

In `config/statamic/cp.php`:

```php
'widgets' => [
    ['type' => 'seo', 'width' => 100],
],
```

It shows the latest report's score and the most recent 404s, to people with the `view seo` permission.

## 6. Give people access

Super users see everything. For other roles, tick the SEO permissions under **Users → Roles**:

| Permission | Lets them |
|---|---|
| `view seo` | open Tools → SEO; with Pro, the reports, the 404 log, the Search Console screen and the widget |
| `manage seo redirects` | create, edit and delete redirects; with Pro, import and export them, and answer the "add a redirect?" question when saving |
| `run seo reports` | start a report (Pro) |

The search and share preview needs no SEO permission, only access to the entry.

## 7. Schedule reports (optional, Pro)

Reports can run daily or weekly (Tools → Addons → SEO → Running). That needs Laravel's scheduler, as for any scheduled task:

```
* * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1
```

## Check that it worked

- View a page's source: one `<title>`, a description, `<link rel="canonical">`, `og:` and `twitter:` tags, and a `<script type="application/ld+json">`.
- Open `/sitemap.xml` and `/robots.txt`; with Pro, `/og.png` (the home page's share card).
- Edit an entry: the SEO tab shows the Google result and the share cards, and they change as you type.
- With several languages: a translated page's source has a `<link rel="alternate" hreflang="…">` for each language, and the sitemap an `<xhtml:link>` for each.
- With Pro: visit a page that doesn't exist, then **Tools → SEO → 404s**: the path is listed. (Visit it in a browser; `curl` counts as a bot and isn't logged.)
- With Pro: **Tools → SEO → Reports → Run report.** A few hundred pages take under a minute.

If something doesn't, see [troubleshooting.md](troubleshooting.md).
