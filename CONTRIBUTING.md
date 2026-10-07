# Working on Marketing Toolkit

```bash
composer install && npm install
npm run build      # Vue → resources/dist/build; commit the build, sites don't run npm
composer test      # Pest, in parallel (about half a minute); the share-card tests skip without PHP's imagick extension
composer lint      # Pint; `vendor/bin/pint` fixes what it finds
composer analyse   # Larastan, level 5, with the 1 GB it needs; phpstan.neon says why each ignored error is ignored
```

One file or one test, without the parallel runner (so `dump()` and `dd()` show):

```bash
vendor/bin/pest tests/Feature/SitemapTest.php
vendor/bin/pest --filter="adds URLs that are not entries"
```

## Tests

Every test is in `tests/Feature`, one file per area; `tests/Pest.php` sets up a site (one `default` site at `https://example.test/`, the `home`, `pages` and `essays` collections, an `assets` container) and holds the helpers (`entryIn()`, `seoGlobal()`, `metaFor()`, `renderAt()`, `cpUser()`, `multisite()`…).

- Tests run as production (`APP_ENV` in `phpunit.xml`), so the meta tags and robots.txt are what a live site prints. CSRF is switched off in `tests/Pest.php`.
- The cache is an `array` store that serializes, as automatic redirects need. Pages render through `tests/fixtures/views`.
- Statamic matches the site by its absolute URL, so request front-end pages as `https://example.test/…`.
- Blueprints, forms and global sets are files that outlive a test: delete what a test creates, or give it a handle no other test uses.
- Each parallel process gets its own copy of Testbench's skeleton in `/tmp/marketing-toolkit-tests`, so a test may write to `public/` or `resources/`.

The suite runs on SQLite. To run it on Postgres, point it at an empty database: `MT_TEST_DB=pgsql DB_PORT=5432 DB_DATABASE=mt_test vendor/bin/pest` (also `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`; not in parallel, as the processes would share the database). GitHub Actions runs all of this on every push: PHP 8.3 on the oldest versions composer.json allows, PHP 8.4 on the newest, Postgres, and without Imagick, plus `composer validate`, Pint, PHPStan and the build.

## Database and data changes

- Migrations that have shipped stay as they are: never rename, move or edit one, since sites have already run it.
- A new migration's date is later than the last one's, so it runs after them (`php artisan make:migration` uses today's date, which is usually enough).
- A migration changes tables only. A change to sites' content or settings (globals, blueprints, addon settings) is an update script: a subclass of Statamic's `UpdateScript` in `src/UpdateScripts/`, which Statamic finds there and runs once on `composer update` (or `php please updates:run`), on the developer's machine, so the change is committed with the update. `shouldUpdate()` uses `isUpdatingTo('x.y.z')` for a one-off change.
- The one exception is `carry_over_co_seo_settings`, a migration because Statamic doesn't run update scripts for a package that changed its name.

## Release

1. Note the change in [CHANGELOG.md](CHANGELOG.md), with an **Upgrading** list for anything a site must do.
2. `npm run build` and commit `resources/dist`.
3. `git tag -a vX.Y.Z -m vX.Y.Z && git push --follow-tags`. Sites update with `composer update jotham-lec/statamic-marketing-toolkit`; the Marketplace picks the tag up from Packagist.
