# Marketing Toolkit

Marketing Toolkit brings the marketing fundamentals of a website to Statamic in one add-on. It covers SEO with a score, redirects and 404s, your tracking tags, Google Consent Mode, leads from your forms, and the site's icons. It is made for web developers and marketing teams who know what Yoast, Rank Math, and Redirection do on WordPress and want the same on Statamic, without the bloat.

Marketing Toolkit is free and open source. If it is useful to you, please give it a heart on the Statamic Marketplace, because hearts help other people find it. On the Marketplace, the heart button is on this page; anywhere else, you can find the add-on on [my Marketplace page](https://statamic.com/creators/jothamlec).

![The Marketing overview, with the SEO score, the failing checks, recent 404s, redirects, and the brand](https://raw.githubusercontent.com/Jotham-LEC/statamic-marketing-toolkit/main/docs/images/overview.png)

## For marketers

- You control the title, description, share image, canonical address, indexing, and structured data of every page, and a preview shows how the page will look in Google and on social media as you type.
- The defaults work from the start, so every page gets its tags, a sitemap, robots.txt, and llms.txt without anyone filling in a field.
- Anyone on the team can manage redirects, including wildcards and pages that are gone for good. A redirect is added for you when a page moves, missing pages are logged, and a spreadsheet of redirects can be imported in one go.
- Every page is checked and scored out of 100 for its title, description, headings, links, share image, and more. The score is shown on a gauge, and each problem is one click from the entry that fixes it.
- Google Tag Manager, Google Analytics 4, PostHog, the Meta Pixel, and LinkedIn are set up by pasting their IDs into the control panel, and their tags load on the live site only.
- Search Console's numbers appear for each page, every form is sent to your tools as a lead with where it came from, and campaign links get their UTM tags.
- Google Consent Mode v2 works with the cookie banner you already have, with defaults for each region.

## For developers

- It is built on Statamic's own fieldsets, globals, forms, tags, and control panel APIs, and a layout needs only two tags, `<s:mt:head />` and `<s:mt:body />`.
- It puts performance first. There is no front-end script unless a feature that needs one is on, its files are served without sessions or cookies, and its caches clear when content changes.
- SEO, redirects, tracking, favicons, and leads come in one package, with the brand and the marketing settings in one section of the control panel.
- Any feature a site does not use can be switched off, and it is then not loaded at all.
- Every value comes from a class that you can extend, so a site can override any rule.

## Screens

As you type a title or description, the preview shows how the page will look in Google and when it is shared on Facebook, LinkedIn, WhatsApp, and X.

![An entry's SEO tab, with the Google preview and the share cards for Facebook and X](https://raw.githubusercontent.com/Jotham-LEC/statamic-marketing-toolkit/main/docs/images/entry-seo.png)

Every page is checked and scored, and each check lists the pages it flagged. You can export the whole report as a CSV file.

![An SEO report with a score of 98, two failing checks, and the Export CSV button](https://raw.githubusercontent.com/Jotham-LEC/statamic-marketing-toolkit/main/docs/images/report.png)

When a page moves, a redirect from its old address is added for you, and you can add your own as well.

![The redirects list, with a wildcard redirect, a 410, and a redirect marked Automatic](https://raw.githubusercontent.com/Jotham-LEC/statamic-marketing-toolkit/main/docs/images/redirects.png)

If Google Tag Manager and another tool are both set, you are warned that each visit would be counted twice.

![The Tracking tab of the Marketing settings, warning that Tag Manager and Google Analytics are both set](https://raw.githubusercontent.com/Jotham-LEC/statamic-marketing-toolkit/main/docs/images/tracking.png)

## Installation

Install the package, create its tables, and create the Brand and Marketing settings global sets:

```bash
composer require jotham-lec/statamic-marketing-toolkit
php artisan migrate
php please mt:install
```

Then add one tag to your layout's `<head>`, right after `<meta charset>` and the viewport, and one right after `<body>`:

```blade
<s:mt:head />
<s:mt:body />
```

In Antlers, these are `{{ mt:head }}` and `{{ mt:body }}`. [Getting started](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/getting-started.md) walks through the rest, from the SEO fields in your blueprints to the first report. If the site already has its SEO done by hand or with another add-on, follow the [migration checklist](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/migrating.md) instead, or hand the move to an AI agent with the [migration skill](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/agent-skill/README.md).

## Who it's for

Marketing Toolkit suits sites where the marketing team owns SEO, tracking, and integrations. Marketers set titles, redirects, tags, and consent in the control panel. Developers and designers stay on the front end, the app, and the business logic, and they stop getting tickets for a meta description or a GA4 ID.

## How it compares with other add-ons

Statamic has excellent add-ons for each part of this. You may be better served by them in these cases:

- **You want first-party support.** SEO Pro is made by the Statamic team, and the others come from long-standing add-on authors. Marketing Toolkit is one developer's project, supported through GitHub issues.
- **You would rather pick add-ons piece by piece.** A feature you switch off here is not loaded at all, so bundling costs no performance. Still, if you prefer one add-on per job, the ones below do their jobs well.
- **You prefer code written mostly by hand.** Marketing Toolkit is built with heavy AI assistance. Every change runs through the test suite and CI, on SQLite and Postgres, but you should know.

They are made by talented developers, and they are worth trying:

| Add-on | What it covers |
| --- | --- |
| [SEO Pro](https://statamic.com/addons/statamic/seo-pro) | SEO, a sitemap, redirects, 404s, and reports, by the Statamic team |
| [Advanced SEO](https://statamic.com/addons/aerni/advanced-seo) | SEO, sitemaps, redirects, social images, and several sites |
| [Aardvark SEO](https://statamic.com/addons/justkidding96/aardvark-seo) | On-page SEO, schema, and redirects |
| [Redirect](https://statamic.com/addons/rias/redirect) | Redirects and 404s |
| [404 Logger](https://statamic.com/addons/stoffelio/404-logger) | A log of 404 requests |
| [Consent Manager](https://statamic.com/addons/kiwikiwi/consent-manager) | A cookie banner and consent for tracking scripts |
| [Favicons](https://statamic.com/addons/kiwikiwi/favicons) | A set of favicons from one image |
| [AB Tester](https://statamic.com/addons/thoughtco/statamic-ab-tester) | A/B experiments |

## Why it exists

I run several Statamic sites that kept needing the same marketing pieces. I pulled them into one Composer package, so each site gets fixes and features with a `composer update` instead of copied code. Then I made it free and open source, in case it helps you too.

If it saves you time, you can [buy me a coffee](https://buymeacoffee.com/jothamh).

## Free and open source

Everything is free under the MIT licence. There are no editions and no licence key, so every feature is in every install. Several sites and languages need Statamic Pro, which is Statamic's own edition; Marketing Toolkit itself costs nothing.

## Requirements

- PHP 8.3+ is needed, with the `curl`, `dom`, `mbstring`, and `openssl` extensions for link checks, reading pages for reports, text handling, and the Search Console key.
- Statamic 6.34+ is needed. Core is enough, and several sites and languages need Statamic Pro.
- The `imagick` extension draws the generated share cards and the favicons. Without it, `gd` draws the favicons from a PNG or JPEG, but not from an SVG.
- The site needs a database that Laravel can migrate, even if it is a flat-file site, because redirects, the 404 log, and reports live in tables. SQLite is fine.
- The cache store must serialize its values, so `file`, `redis`, `database`, and `memcached` work, but `array` does not.
- Laravel's scheduler runs scheduled reports and the daily Search Console import.
- A queue worker is optional. With one, reports run in the background; without one, they run while their screen is open.
- Node is needed only to develop the add-on, and sites never run npm.

## Documentation

- [Getting started](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/getting-started.md)
- [Moving an existing site](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/migrating.md)
- [Migration skill for AI agents](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/agent-skill/README.md)
- [For editors](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/editors.md)
- [Tracking and Consent Mode](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/tracking.md)
- [Configuration](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/configuration.md)
- [For developers](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/developers.md)
- [Troubleshooting](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/troubleshooting.md)
- [Upgrading from Co-SEO](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/upgrading.md)
- [Changelog](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/CHANGELOG.md)

## Licence and support

Marketing Toolkit is open source under the [MIT licence](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/LICENSE.md), by Jotham Lim (CoThinking). Please send questions and bug reports to [GitHub issues](https://github.com/Jotham-LEC/statamic-marketing-toolkit/issues). The latest release is on [Packagist](https://packagist.org/packages/jotham-lec/statamic-marketing-toolkit), and the tests run on [GitHub Actions](https://github.com/Jotham-LEC/statamic-marketing-toolkit/actions/workflows/tests.yml).
