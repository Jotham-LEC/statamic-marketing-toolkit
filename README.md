# statamic-seo

Private Statamic 6 addon: meta tags, Open Graph and X cards, JSON-LD, sitemap, robots.txt, humans.txt, trailing-slash redirects, and **generated Open Graph images** with per-entry overrides. Built to compare against SEO Pro and to share between Jotham's sites. Statamic Core is enough; nothing here needs Pro.

## Install

The repo is private. Composer reads it with a GitHub token that has read access to it:

```bash
composer config --global github-oauth.github.com <token>     # once per machine
```

In the site's `composer.json`:

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/Jotham-LEC/statamic-seo" }]
```

Then:

```bash
composer require jotham-lec/statamic-seo
php please seo:install                      # creates the "SEO & brand" global set
php artisan vendor:publish --tag=seo-config # optional: config/seo.php
```

CI and servers need the same token: `COMPOSER_AUTH='{"github-oauth":{"github.com":"<token>"}}'`.

## Use

1. Import the fieldset into each blueprint that should have SEO fields: `- import: seo::seo` (one `seo` group: title, description, share image, card title/subtitle/template, og:type, canonical, noindex, nofollow, in sitemap, extra JSON-LD).
2. Put the tag in the layout's `<head>`:

   ```blade
   <s:seo:meta />                                         {{-- reads the view's $page --}}
   <s:seo:meta :entry="$entry" />                         {{-- a layout that is passed the entry --}}
   <s:seo:meta title="Contact" description="Write to us." /> {{-- a page with no entry --}}
   <s:seo:meta :canonical="false" status="404" />         {{-- an error page --}}
   ```

3. Fill in **Globals → SEO & brand**: site name, default description and image, publisher (Organization, LocalBusiness or Person), verification codes, robots.txt lines, humans.txt, share-card colours and picture.

## What each value falls back to

| Value | Order |
|---|---|
| `<title>` | SEO title as typed → `{title}{separator}{site}` if it fits `seo.title.max`, else the title → site name on home. `· Page N` past page 1 |
| description | SEO description → `description` field → `description_fields` → first paragraph of `content` (skipping `skip_prefixes`) → global default. Cut to 155 on a word |
| share image | template `image` → SEO share image → `image_fields` → **generated card** → global default image. Uploads are cropped to 1200×630 JPEG through Glide |
| canonical | template `canonical` (`false` for none) → SEO canonical (a piece first published elsewhere) → the page, with `?page=N` |
| robots | noindex when: SEO noindex, not production, a `noindex_params` query, a `noindex_routes` route, a 4xx status, or your `shouldNoindex()` |

Empty fields count as unset.

## Generated share cards

`/og.png` (home) and `/og/{uri}.png` draw the entry's card with [simonhamp/the-og](https://github.com/simonhamp/the-og), cached until the entry or the colours change, served without a cookie so CDNs can cache it.

- **Editors** change the card's text (Card title, Card subtitle), pick another template (Card template), or upload a Share image, which replaces the card.
- **Developers** add templates: extend `JothamLec\Seo\Og\Template`, return a the-og `Image` built from the `Card`, and register it in `seo.og.templates`. A collection picks one with `og_template`. Bump `version()` when the design changes.

## Control panel

- **Search and share preview.** The fieldset's first field shows the Google result, the Facebook/LinkedIn/WhatsApp card and the X card, updated as the editor types, with title and description counters (limits: `seo.title.min`/`max`, `seo.description.min`/`length`). The values come from the site's own `SiteSeo` rules, run on the unsaved form, so fallbacks show as they will on the page. A generated card is drawn from the form and never cached. Add the field anywhere else as `type: seo_preview`.
- **Tools → SEO**: what the site serves; Redirects; 404s; a link to the brand global.
- **Dashboard widget**: add `['type' => 'seo']` to `widgets` in `config/statamic/cp.php`. It shows the latest report score and the most recent 404s once those exist.
- **Permissions** (Users → Roles → SEO): `view seo` (the SEO screens and the widget), `manage seo redirects`, `run seo reports`. The preview needs no SEO permission, only access to the entry.

## Redirects and 404s

`php artisan migrate` creates `seo_redirects` and `seo_404s`. Settings are in `config/seo.php` (`redirects`, `not_found`).

- **Rules apply only to addresses that would be a 404**, so a page that exists always wins. Exact sources first, then `*` wildcards, the longest source first; `$1`, `$2`… in the target are what each `*` matched. 301, 302 or 410 (which shows the site's error page with status 410). The visitor's query string is passed on. Rules are cached and rebuilt when one changes.
- **Tools → SEO → Redirects**: list, search, create, edit, delete; CSV export and import (`source,target,status,active`; import adds or updates by source and reports bad rows by line).
- **Automatic 301s** when published content moves: an entry's or a term's slug or date changes, or a page moves in a collection's tree (with the pages under it). Moving or renaming a collection's mount page adds one wildcard rule for that collection's entries. Chains don't build up: rules into the old address follow it, and a rule out of an address that is live again is dropped. Edited by hand, an automatic rule becomes a manual one.
- **The save dialog**: saving an entry or term form that changes its address asks people with `manage seo redirects` whether to add the 301 (Add redirect, Don't add, or Don't save yet). Saves without the dialog (other people, code, imports) add it.
- **Tools → SEO → 404s**: one row per missing path (hits, first and last seen, last referrer), the most recent `max_rows` kept; bot user agents and scanner probes (`*.php`, `/wp-*`, `/.env*`…) are not logged. A row's menu creates a redirect from it.

The automatic redirects compare a content item with the state it was loaded in, which needs a cache store that serializes (file, Redis, database; not `array`).

## Per-site rules

Extend `JothamLec\Seo\SiteSeo` and set `seo.class`. Each value is one public method: override `extraNodes()` for Product/Offer JSON-LD, `shouldNoindex()` for an empty listing, `additionalSitemapUrls()` for controller pages, `title()`, `description()`, and so on. Per collection, `config/seo.php` sets `og_type`, `schema` (Article…), `page_schema`, `description_fields`, `image_fields`, `faq_field` and `og_template`.

## Develop

```bash
composer install
npm install
npm run build      # the CP's Vue components → resources/dist/build (committed; sites don't run npm)
vendor/bin/pest    # the share-card tests need PHP's imagick extension
vendor/bin/pint
```

Sites pick up new CP assets on `composer update` (Statamic republishes them); otherwise run `php artisan vendor:publish --tag=seo --force`.

Release: `git tag vX.Y.Z && git push --tags`; sites update with `composer update jotham-lec/statamic-seo`.

## Not yet built

Phase 5 of the spec: reports and grading (configured through Statamic's addon settings) and the report score on the dashboard widget; also `sitemap.xsl`, and the `seo:import-runway` command for moojing's Runway redirects (left for its rollout). Multi-site defaults and GraphQL fields need Statamic Pro and are left out.

Licence: proprietary, all rights reserved.
