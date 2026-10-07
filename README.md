# Marketing Toolkit

[![Latest version](https://img.shields.io/packagist/v/jotham-lec/statamic-marketing-toolkit)](https://packagist.org/packages/jotham-lec/statamic-marketing-toolkit)
[![Downloads](https://img.shields.io/packagist/dt/jotham-lec/statamic-marketing-toolkit)](https://packagist.org/packages/jotham-lec/statamic-marketing-toolkit)
[![Tests](https://github.com/Jotham-LEC/statamic-marketing-toolkit/actions/workflows/tests.yml/badge.svg)](https://github.com/Jotham-LEC/statamic-marketing-toolkit/actions/workflows/tests.yml)
![Statamic 6.34+](https://img.shields.io/badge/Statamic-6.34%2B-ff269e)
![PHP 8.3+](https://img.shields.io/packagist/dependency-v/jotham-lec/statamic-marketing-toolkit/php)
[![Licence](https://img.shields.io/packagist/l/jotham-lec/statamic-marketing-toolkit)](LICENSE.md)
[![Buy me a coffee](https://img.shields.io/badge/Buy%20me%20a%20coffee-support-ffdd00?logo=buymeacoffee&logoColor=black)](https://buymeacoffee.com/jothamh)

The marketing fundamentals for a Statamic website, in one addon: SEO with a score, redirects and 404s, your tracking tags, Google Consent Mode, leads from your forms, and the site's icons. For web developers and marketing teams who know what Yoast, Rank Math and Redirection do on WordPress, and want the same on Statamic, without the bloat.

![The SEO score on Tools → SEO](https://raw.githubusercontent.com/Jotham-LEC/statamic-marketing-toolkit/main/docs/images/overview.png)

## For marketers

- **Granular control over SEO**: title, description, share image, canonical, noindex and structured data for every page, with a live Google and social preview as you type.
- **Sensible defaults**: install and go. Every page gets its tags, a sitemap, robots.txt and llms.txt without anyone filling in a field.
- **404s and redirects**: redirects anyone can manage, wildcards included, a redirect added when a page moves, the list of missing pages, and a spreadsheet import.
- **A score and a report**: every page checked and scored out of 100 (titles, descriptions, headings, broken links, share images and more), on a gauge, with each issue one click from its fix.
- **Your tracking tags**: Google Tag Manager, Google Analytics 4, PostHog, the Meta Pixel and LinkedIn, pasted into the control panel, on the live site only.
- **Integrations**: Google Search Console numbers per page, every form sent to your tools as a lead with where it came from, and campaign links with UTM tags.
- **Consent**: Google Consent Mode v2 for the cookie banner you already have, by region.

## For developers

- **Statamic native**: fieldsets, globals, forms, tags and the control panel, extended through Statamic's own APIs. Two tags in the layout: `<s:mt:head />` and `<s:mt:body />`.
- **Performance first**: no front-end script unless a feature that needs one is on, files served without sessions or cookies, and caches that clear when content changes.
- **Batteries included, no bloat**: SEO, redirects, tracking, favicons and leads in one package, with one set of brand settings.
- **Feature toggles**: switch off what a site doesn't use, and it isn't loaded at all.
- **Overridable rules**: every value comes from a class you can extend.

## Who it's for

Sites where the marketing team owns SEO, tracking and integrations. Marketers set titles, redirects, tags and consent in the control panel. Developers and designers stay on the front end, the app and the business logic, and stop getting tickets for a meta description or a GA4 ID.

## How it compares with other add-ons

Statamic has excellent add-ons for each part of this. You may be better served by them if:

- **You want first-party support.** SEO Pro is made by the Statamic team, and the others come from long-standing add-on authors. Marketing Toolkit is one developer's project, supported through GitHub issues.
- **You'd rather pick add-ons piece by piece.** A module you switch off here isn't loaded at all, so bundling costs no performance. Still, if you prefer one add-on per job, the ones below do their jobs well.
- **You prefer code written mostly by hand.** Marketing Toolkit is built with heavy AI assistance. Every change runs through the test suite and CI, on SQLite and Postgres, but you should know.

They're made by talented developers, and they're worth trying:

| Add-on | What it covers |
| --- | --- |
| [SEO Pro](https://statamic.com/addons/statamic/seo-pro) | SEO, sitemap, redirects, 404s and reports, by the Statamic team |
| [Advanced SEO](https://statamic.com/addons/aerni/advanced-seo) | SEO, sitemaps, redirects, social images and multi-site |
| [Aardvark SEO](https://statamic.com/addons/justkidding96/aardvark-seo) | On-page SEO, schema and redirects |
| [Redirect](https://statamic.com/addons/rias/redirect) | Redirects and 404s |
| [404 Logger](https://statamic.com/addons/stoffelio/404-logger) | A log of 404 requests |
| [Consent Manager](https://statamic.com/addons/kiwikiwi/consent-manager) | A cookie banner and consent for tracking scripts |
| [Favicons](https://statamic.com/addons/kiwikiwi/favicons) | A favicon set from one image |
| [AB Tester](https://statamic.com/addons/thoughtco/statamic-ab-tester) | A/B experiments |

## Why it exists

I run several Statamic sites that kept needing the same marketing pieces. I pulled them into one Composer package, so each site gets fixes and features with a `composer update` instead of copied code. Then I made it free and open source, in case it helps you too.

If it saves you time, you can [buy me a coffee](https://buymeacoffee.com/jothamh).

## Free and open source

Everything is free, under the MIT licence. There are no editions and no licence key: every feature is in every install. Several sites and languages need Statamic Pro, Statamic's own edition; Marketing Toolkit itself costs nothing.

## Requirements

- **PHP 8.3+** with the `curl`, `dom`, `mbstring` and `openssl` extensions (link checks, reading pages for reports, text, the Search Console key).
- **Statamic 6.34+**. Core is enough; several sites and languages need Statamic Pro.
- **`imagick`** for the generated share cards and the favicons; without it, **`gd`** draws the favicons, from a PNG or JPEG but not an SVG.
- **A database** Laravel can migrate, even on a flat-file site: redirects, the 404 log and reports live in tables. SQLite is fine.
- **A cache store that serializes** (`file`, `redis`, `database`, `memcached`), not `array`.
- **Laravel's scheduler** for scheduled reports and the daily Search Console import.
- **A queue worker**, optionally, to run reports in the background; without one they run while their screen is open.
- **Node** only to develop the addon; sites never run npm.

## Documentation

[Getting started](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/getting-started.md) · [For editors](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/editors.md) · [Tracking and Consent Mode](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/tracking.md) · [Configuration](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/configuration.md) · [For developers](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/developers.md) · [Troubleshooting](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/troubleshooting.md) · [Upgrading from Co-SEO](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/upgrading.md) · [Changelog](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/CHANGELOG.md)

## Licence and support

Marketing Toolkit is open source under the [MIT licence](LICENSE.md), by Jotham Lim (CoThinking). Questions and bug reports: [GitHub issues](https://github.com/Jotham-LEC/statamic-marketing-toolkit/issues). If it helps you, you can [buy me a coffee](https://buymeacoffee.com/jothamh).
