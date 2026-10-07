# Getting started

From nothing to a site with meta tags, a sitemap, share cards, redirects and reports. Allow about twenty minutes.

## What you need

- **Statamic 6.34+** (Core is enough) on **PHP 8.3+**, with the PHP extensions in [the README's requirements](../README.md#requirements). Several sites and languages need Statamic Pro, as Statamic itself does, and Marketing Toolkit Pro; see [Several sites and languages](developers.md#several-sites-and-languages).
- **PHP's `imagick` extension** for the generated share cards (Marketing Toolkit Pro). Without it the cards (and their tests) fail and favicons are drawn with `gd`, from a PNG or JPEG only; everything else works.
- **A database** Laravel can migrate. Redirects, the 404 log and reports live in tables, even on a flat-file site; SQLite is fine.
- **A cache store that serializes**: `file`, `redis`, `database` or `memcached`. Not `array`: automatic redirects need to compare an entry with the copy loaded before it was edited, and the `array` store hands back the same object.
- **For Pro, a licence** for the live site, from the [Statamic Marketplace](https://statamic.com/addons/jothamlec/marketing-toolkit). Local and staging sites don't need one.

## The editions

Marketing Toolkit comes as **Free** and **Pro**. Pro is $39 per site. A licence covers every release of one major version (all of 1.x, for example), and one bought during 0.x also covers 1.x. A new major version needs a new licence.

| | Free | Pro |
|---|:---:|:---:|
| **SEO** | | |
| Titles, descriptions, Open Graph and X cards, structured data (JSON-LD) | ✓ | ✓ |
| Live Google and social preview as you type | ✓ | ✓ |
| Sitemap, robots.txt, llms.txt and ads.txt | ✓ | ✓ |
| IndexNow: Bing and others told of every change | ✓ | ✓ |
| Share cards generated for every page | | ✓ |
| **Score and reports** | | |
| Every page scored out of 100, with a report, on a schedule | | ✓ |
| Google Search Console numbers per page | | ✓ |
| Dashboard widget | | ✓ |
| **Redirects and 404s** | | |
| Redirects by hand, with wildcards and 410s | ✓ | ✓ |
| A redirect added when a page's address changes | | ✓ |
| The 404 log: the pages people couldn't find | | ✓ |
| CSV import and export of redirects | | ✓ |
| **Tracking and consent** | | |
| Google Tag Manager, Analytics 4, PostHog, Meta Pixel and LinkedIn tags | ✓ | ✓ |
| Google Consent Mode v2, by region | | ✓ |
| Consent for Meta, LinkedIn and PostHog without GTM | | ✓ |
| **Leads and campaigns** | | |
| Form submissions sent to your tools as leads, with where they came from | | ✓ |
| Campaign links with UTM tags | | ✓ |
| **Sites and languages** | | |
| Several sites and languages, with hreflang | Default site only: no hreflang, and the sitemap, robots.txt and icons on its domain alone ([details](developers.md#several-sites-and-languages)) | ✓ |
| **For developers** | | |
| Favicons and web app manifest from one image | ✓ | ✓ |
| Any value overridden in code (`SiteSeo`, `Tracking`) | ✓ | ✓ |
| Switch off the modules a site doesn't use | In `config/seo.php` | Also in the control panel |

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

`seo:install` uses the first asset container for the logo, default image and icon; pass `--container=handle` to choose another. It also fills the empty brand fields with what the site already uses (the home page's description, the control panel kept out of robots.txt), so they show in the control panel ready to change. Running it again overwrites nothing: it adds only what is missing, such as the fields a newer version brings. With several sites it creates the set on each, the others taking what they leave empty from the default site; for a set that already exists, it offers to enable it on the sites it's missing from.

**A new Statamic site has a `public/robots.txt` and an empty `public/favicon.ico`.** The web server answers with those files before the addon sees the request, so the addon's robots.txt (with its `Sitemap:` line) and icons never show. `seo:install` names them and offers to delete them; Tools → SEO marks them too. The same goes for `llms.txt`, `ads.txt` and the other icon files.

The control panel's scripts and styles are published to `public/vendor/statamic-marketing-toolkit` when Composer installs or updates the package. If the SEO screens look unstyled, publish them yourself: `php artisan vendor:publish --tag=marketing-toolkit --force`.

## 2. Add the SEO fields to your blueprints

In each blueprint whose pages should have SEO fields (usually every collection with a route), add the fieldset where you want it, typically on its own tab. In the control panel: **Blueprints**, the collection's blueprint, **Add Tab** "SEO", then **Link Fieldset** and choose **SEO**. A new Statamic site's Pages collection has no blueprint file yet; opening it there creates one.

Or in the blueprint's YAML (`resources/blueprints/collections/{collection}/{blueprint}.yaml`), as a tab beside the ones it has:

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

In the layout, remove your own `<title>`, description, canonical, Open Graph and JSON-LD tags, and any Google Tag Manager, Google Analytics, PostHog, Meta Pixel or LinkedIn snippets. Then add one tag in the `<head>`, right after `<meta charset>` and the viewport, and one right after `<body>`:

```blade
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <s:seo:head />
    …
</head>
<body>
    <s:seo:body />
```

or in Antlers, `{{ seo:head }}` and `{{ seo:body }}`.

`<meta charset>` must come first: browsers look for it in the first 1024 bytes, and the tracking scripts alone can take several thousand.

`seo:head` prints the Consent Mode defaults and tracking tags first, since Google's tags must load before anything else that uses them, then the meta tags. `seo:body` prints the tags' `<noscript>` fallbacks (Google Tag Manager's needs to be in the body). A site that only wants the meta tags can use `<s:seo:meta />` instead of `seo:head`, as before.

Pages that aren't Statamic entries (a controller page, a 404 view) pass what they know to either:

```blade
<s:seo:head title="Contact" description="Write to us." />
<s:seo:head :canonical="false" status="404" />
```

All the parameters are in [developers.md](developers.md#the-tag).

## 4. Fill in "SEO & brand"

In the control panel, open **Globals → SEO & brand**: separator, default description and share image, who publishes the site (organisation, local business or person), tracking IDs and Consent Mode, verification codes, robots.txt lines, humans.txt and the share-card colours. [editors.md](editors.md#seo--brand) explains each field.

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

Reports can run daily or weekly (Tools → SEO → Report settings → Running). That needs Laravel's scheduler, as for any scheduled task:

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

## After every update

```bash
composer update jotham-lec/statamic-marketing-toolkit
php artisan migrate
```

Statamic runs the addon's update scripts on `composer update` (or `php please updates:run`): they add the fields a new version brings to SEO & brand, and make any change it needs to your settings. Commit the files they change (the blueprint in `resources/blueprints/globals`, the global set in `content/globals`). The control panel's scripts are republished at the same time. [CHANGELOG.md](../CHANGELOG.md) says what each version needs beyond that, under **Upgrading**.
