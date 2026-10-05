# Getting started

From nothing to a site with meta tags, a sitemap, share cards, redirects and reports. Allow about twenty minutes.

## What you need

- **Statamic 6** (Core is enough; nothing here needs Pro) on **PHP 8.3+**.
- **PHP's `imagick` extension** for the generated share cards. Without it the cards (and their tests) fail; everything else works.
- **A database** Laravel can migrate. Redirects, the 404 log and reports live in tables, even on a flat-file site; SQLite is fine.
- **A cache store that serializes**: `file`, `redis`, `database` or `memcached`. Not `array`: automatic redirects need to compare an entry with the copy loaded before it was edited, and the `array` store hands back the same object.
- **A GitHub token** with read access to `Jotham-LEC/statamic-co-seo`, because the package is private.

## 1. Install the package

Give Composer the token once per machine (and as `COMPOSER_AUTH` in CI and on servers):

```bash
composer config --global github-oauth.github.com <token>
```

Add the repository to the site's `composer.json`:

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/Jotham-LEC/statamic-co-seo" }]
```

Then:

```bash
composer require jotham-lec/statamic-co-seo
php artisan migrate                          # seo_redirects, seo_404s, seo_reports, seo_report_pages
php please seo:install                       # the "SEO & brand" global set
php artisan vendor:publish --tag=seo-config  # optional: config/seo.php, to change the defaults
```

`seo:install` uses the first asset container for the logo and default image; pass `--container=handle` to choose another. Running it again changes nothing that exists.

The control panel's scripts and styles are published to `public/vendor/statamic-co-seo` when Composer installs or updates the package. If the SEO screens look unstyled, publish them yourself: `php artisan vendor:publish --tag=seo --force`.

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

This adds the **search and share preview** and one `seo` group: title, description, share image, card title and subtitle, card template, og:type, canonical URL, noindex, nofollow, "in sitemap" and extra JSON-LD. Every field is optional; empty means "use the default". See [What each value falls back to](../README.md#what-each-value-falls-back-to).

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

In the control panel, open **Globals → SEO & brand**: site name, default description and share image, who publishes the site (organisation, local business or person), verification codes, robots.txt lines, humans.txt and the share-card colours. [editors.md](editors.md#seo--brand) explains each field.

## 5. Add the dashboard widget (optional)

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
| `view seo` | open Tools → SEO, the reports, the 404 log and the widget |
| `manage seo redirects` | create, edit, import and delete redirects; answer the "add a redirect?" question when saving |
| `run seo reports` | start a report |

The search and share preview needs no SEO permission, only access to the entry.

## 7. Schedule reports (optional)

Reports can run daily or weekly (Tools → Addons → SEO → Running). That needs Laravel's scheduler, as for any scheduled task:

```
* * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1
```

## Check that it worked

- View a page's source: one `<title>`, a description, `<link rel="canonical">`, `og:` and `twitter:` tags, and a `<script type="application/ld+json">`.
- Open `/sitemap.xml`, `/robots.txt` and `/og.png` (the home page's share card).
- Edit an entry: the SEO tab shows the Google result and the share cards, and they change as you type.
- Visit a page that doesn't exist, then **Tools → SEO → 404s**: the path is listed. (Visit it in a browser; `curl` counts as a bot and isn't logged.)
- **Tools → SEO → Reports → Run report.** A few hundred pages take under a minute.

If something doesn't, see [troubleshooting.md](troubleshooting.md).
