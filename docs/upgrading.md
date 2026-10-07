# Upgrading

## From 0.19: the `seo` names

Up to 0.19 most of the addon's names were `seo`. They are now `marketing-toolkit` (the addon's slug, wherever Statamic names a thing after it) and `mt` (wherever you type a short handle); see [the names](developers.md#names). Most of the move happens by itself.

### What happens by itself

On `composer update` (on your machine), Statamic runs the addon's update scripts. They rename, in the site's own files:

- `import: seo::seo` in blueprints and fieldsets → `marketing-toolkit::seo`, and the `seo::` labels in the SEO & brand blueprint and the forms' lead source fields → `marketing-toolkit::`.
- `<s:seo:head />`, `{{ seo:head }}` and the other tags in `resources/views` → `<s:mt:head />`, `{{ mt:head }}`.
- The widget in `config/statamic/cp.php`: `'type' => 'seo'` → `'type' => 'mt'`.
- `config/seo.php` → `config/marketing-toolkit.php`, as it was: its old keys keep working.
- `lang/vendor/seo` → `lang/vendor/marketing-toolkit`.
- The permissions of each role: `view seo`, `manage seo redirects`, `run seo reports` → `view marketing toolkit`, `manage marketing toolkit redirects`, `run marketing toolkit reports`.

The command lists what it changed. **Commit it**, then deploy.

On `php artisan migrate` (on the server, as every deploy runs it), the tables `seo_*` become `mt_*`, the reports kept are rewritten to the new translation keys, and a Search Console key uploaded in the control panel moves to `storage/app/private/marketing-toolkit`.

### What to check by hand

- **Anything that names the old ones in code**: a template that builds the tag another way (`Statamic::tag('seo:head')`), `route('seo.…')` (now `mt.…`), `__('seo::…')` (now `marketing-toolkit::…`), a query on `seo_redirects` (now `mt_redirects`), a scheduler or deploy script that runs `php please seo:…` (now `mt:…`).
- **`.env`**: the names are now `MT_GTM_ID`, `MT_SEARCH_CONSOLE_CREDENTIALS` and so on. The `SEO_…` names are still read until 1.0, so nothing stops working; rename them on each server when convenient.
- **Roles kept in the database** (Statamic's Eloquent driver for users): the update script renames the permissions in the database it runs against. For production's, run it there too: `php please updates:run 0.19.0 --package=jotham-lec/statamic-marketing-toolkit`.
- **Bookmarks**: the control panel's screens moved from `/cp/seo/…` to `/cp/marketing-toolkit/…`.
- **Listing columns** chosen on Redirects, 404s and a report reset once (they're kept per user under a new name).

## From Co-SEO

Co-SEO is now **Marketing Toolkit**: a site changes its Composer package and the key in `editions.php`, and the update scripts and migrations above do the rest.

### 1. Swap the package

```bash
composer remove jotham-lec/statamic-co-seo --no-update
composer require jotham-lec/statamic-marketing-toolkit
php please updates:run 0.19.0 --package=jotham-lec/statamic-marketing-toolkit
php artisan migrate
```

The new package replaces the old one, so Composer won't install both. `jotham-lec/statamic-co-seo` has been removed from Packagist. Statamic runs a package's update scripts only when it updates that package, not when it's installed under a new name, hence `updates:run`. Then follow [From 0.19](#from-019-the-seo-names) for what to check.

`php artisan migrate` also copies the addon settings (the Search Console property) to the new name, from `resources/addons/seo.yaml` to `resources/addons/marketing-toolkit.yaml`, or, with Statamic's Eloquent driver, from the `addon_settings` row of `jotham-lec/statamic-co-seo`. Commit the new file if you keep it in git.

### 2. Rename the edition key

In `config/statamic/editions.php`, the key is the package name:

```php
'addons' => [
    'jotham-lec/statamic-marketing-toolkit' => 'pro',
],
```

A site without this line runs as Free. Since Co-SEO 0.17 that leaves out the reports, the 404 log, automatic redirects and generated share cards, so check every site that used them.

### 3. What else changes

- The control panel's scripts are published to `public/vendor/statamic-marketing-toolkit`, with the publish tag `marketing-toolkit`: `php artisan vendor:publish --tag=marketing-toolkit --force`.
- PHP classes moved from `JothamLec\Seo\…` to `JothamLec\MarketingToolkit\…`. Only a site that overrides `SiteSeo` or extends a share-card template needs to change its `use` lines.

### 4. Use the new modules

- **Tracking and Consent Mode**: run `php please mt:install --tab=tracking`, swap `<s:mt:meta />` for `<s:mt:head />` (right after `<meta charset>` and the viewport), add `<s:mt:body />` right after `<body>`, then move the site's tracking IDs into the **Tracking** tab (or `.env`) and delete its own snippets, or each visit counts twice. See [tracking.md](tracking.md).
- **Favicons**: upload the icon in **SEO & brand → Brand → Icon** (after `mt:install`), then delete the old `favicon.ico`, `apple-touch-icon.png` and `site.webmanifest` from `public/` and their `<link>` tags from the layout; files in `public/` win over the generated ones.
- **Leads (Pro)**: run `php please mt:install --forms` to add the lead source fields to every form. A site that sends its own lead events (from Livewire forms, say) should call `window.mtConversion('form-handle')` instead, or leave **Send form submissions as leads** off.
