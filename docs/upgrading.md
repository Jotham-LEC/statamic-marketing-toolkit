# Upgrading from Co-SEO

Co-SEO is now **Marketing Toolkit**. It is the same addon with a new name, so a site changes its Composer package and the key in `editions.php`, and nothing else.

## 1. Swap the package

```bash
composer remove jotham-lec/statamic-co-seo --no-update
composer require jotham-lec/statamic-marketing-toolkit
php artisan migrate
```

The new package replaces the old one, so Composer won't install both. `jotham-lec/statamic-co-seo` is abandoned on Packagist and points to the new package.

## 2. Rename the edition key

In `config/statamic/editions.php`, the key is the package name:

```php
'addons' => [
    'jotham-lec/statamic-marketing-toolkit' => 'pro',
],
```

A site without this line runs as Free. Since Co-SEO 0.17 that leaves out the reports, the 404 log, automatic redirects and generated share cards, so check every site that used them.

## 3. What stays the same

- `config/seo.php`, its keys, and `php artisan vendor:publish --tag=seo-config`.
- The `<s:seo:meta />` tag (and `{{ seo:meta }}`).
- The `seo::seo` fieldset your blueprints import, the **SEO & brand** global (`seo`), and every field's data.
- The `seo::` translation keys and `--tag=seo-translations`.
- Control panel addresses (`/cp/seo/…`), route names (`seo.*`) and permissions (`view seo`, `manage seo redirects`).
- The database tables (`seo_redirects`, `seo_404s`, `seo_reports`, `seo_search_stats`).
- The addon settings (the Search Console property): copied from `resources/addons/seo.yaml` to `resources/addons/marketing-toolkit.yaml` the first time the site boots. Commit the new file.

## 4. What changes


- The control panel's scripts are published to `public/vendor/statamic-marketing-toolkit`. Their publish tag is now `marketing-toolkit`: `php artisan vendor:publish --tag=marketing-toolkit --force`.
- PHP classes moved from `JothamLec\Seo\…` to `JothamLec\MarketingToolkit\…`. Only a site that overrides `SiteSeo` (`'class'` in `config/seo.php`) or extends a share-card template needs to change its `use` lines.

## 5. Use the new modules

- **Tracking and Consent Mode**: run `php please seo:install --fields --tab=tracking`, swap `<s:seo:meta />` for `<s:seo:head />` at the top of the `<head>`, add `<s:seo:body />` right after `<body>`, then move the site's tracking IDs into the **Tracking** tab (or `.env`) and delete its own snippets, or each visit counts twice. See [tracking.md](tracking.md).
- **Favicons**: upload the icon in **SEO & brand → Brand → Icon** (after `seo:install --fields`), then delete the old `favicon.ico`, `apple-touch-icon.png` and `site.webmanifest` from `public/` and their `<link>` tags from the layout; files in `public/` win over the generated ones.
- **Leads (Pro)**: run `php please seo:install --forms` to add the lead source fields to every form. A site that sends its own lead events (moojing's Livewire forms) should call `window.mtConversion('form-handle')` instead, or leave **Send form submissions as leads** off.
