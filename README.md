# statamic-co-seo

SEO for Statamic 6 sites: meta tags, Open Graph and X cards, JSON-LD, sitemap, robots.txt, **generated share images**, a live search-and-share preview in the control panel, redirects with automatic 301s, a 404 log, and SEO reports with scores. Statamic Core is enough; nothing here needs Pro.

Private package (`jotham-lec/statamic-co-seo`). Built for fresh Statamic sites: Statamic's own defaults first, and anything one site needs goes in that site's `SiteSeo` subclass.

## Documentation

| | |
|---|---|
| [Getting started](docs/getting-started.md) | Requirements, install, blueprints, the tag, permissions, and a checklist that it works. |
| [Configuration](docs/configuration.md) | Every key of `config/seo.php`, the report settings, permissions and commands. |
| [A guide for editors](docs/editors.md) | For the people who write the pages: the SEO fields, the preview, redirects, 404s and reports. No code. |
| [For developers](docs/developers.md) | How values are worked out, overriding rules in a `SiteSeo` subclass, the tag, share-card templates, working on the addon. |
| [Troubleshooting](docs/troubleshooting.md) | Problems people have hit, and their fixes. |
| [Changelog](CHANGELOG.md) | What changed in each version. |

## Quick start

```bash
composer config --global github-oauth.github.com <token>   # read access to the private repo
```

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/Jotham-LEC/statamic-co-seo" }]
```

```bash
composer require jotham-lec/statamic-co-seo
php artisan migrate
php please seo:install
```

1. Import the fieldset into each blueprint that should have SEO fields: `- import: seo::seo`.
2. Put the tag in the layout's `<head>`: `<s:seo:meta />` (Antlers: `{{ seo:meta }}`).
3. Fill in **Globals → SEO & brand**.

[Getting started](docs/getting-started.md) covers the rest: the dashboard widget, permissions, the schedule.

## What it does

- **On every page**: the `<title>`, description, robots, canonical, Open Graph, X and verification tags, and one JSON-LD graph (website, publisher, page, breadcrumbs, article, FAQ and your own nodes). Escaped for HTML and JSON.
- **Files**: `/sitemap.xml` (split above 1,000 URLs, cached, rebuilt on save) and `/robots.txt` from the global set.
- **Share cards**: a picture drawn for each page that has none, at `/og/{uri}.png`, in the brand's colours; editors change its text or replace it with an upload.
- **In the control panel**:
  - a live **Google and share preview** with length counters on every page
  - **Redirects** (wildcards, 301/302/410, CSV), added automatically when a page's address changes, with a question first
  - a **404 log** with one-click redirects
  - **reports** that render every page, check it against thirteen rules and score the site
  - **Google Search Console** numbers per page (clicks, appearances, position), imported daily
  - a **dashboard widget**
- **Rules in code**: one class, `SiteSeo`, works out every value; extend it to change one rule for one site.

## What each value falls back to

| Value | Order |
|---|---|
| `<title>` | SEO title as typed → `{title}{separator}{site}` if it fits `seo.title.max`, else the title → site name on home. `· Page N` past page 1 |
| description | SEO description → `description` field → `description_fields` → first paragraph of `content` → global default. Cut to 155 on a word |
| share image | template `image` → SEO share image → `image_fields` → **generated card** → global default image. Uploads are cropped to 1200×630 JPEG through Glide |
| canonical | template `canonical` (`false` for none) → SEO canonical (a piece first published elsewhere) → the page, with `?page=N` |
| robots | noindex when: SEO noindex, not production, a `noindex_params` query, a `noindex_routes` route, a 4xx status, or your `shouldNoindex()` |

Empty fields count as unset.

## Not built yet

- `/sitemap.xsl` (a readable sitemap in the browser).
- Multi-site defaults and GraphQL fields: they need Statamic Pro, and none of the sites runs it.

## Develop

```bash
composer install && npm install
npm run build      # commit resources/dist: sites don't run npm
vendor/bin/pest    # needs PHP's imagick extension
vendor/bin/pint
```

Release: `git tag vX.Y.Z && git push --tags`; sites update with `composer update jotham-lec/statamic-co-seo`.

Licence: proprietary, all rights reserved.
