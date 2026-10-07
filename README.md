# Marketing Toolkit

I built Marketing Toolkit because several marketing-centric websites that I've built have common feature requirements, and I find myself replicating many of them. This project aims to refactor it out into packages that I can replicate across many of my projects -- and I'm sharing it here so that your team can benefit too.

Built for teams where marketing has ownership over SEO & Analytics, so that designers/developers can focus on app logic and design. I try to keep this project as lean, performant, & bloat-free, whilst still flexible for universal projects as best as I can.


**If this is useful to you, please give it a heart on the Statamic Marketplace.**  You can find the add-on on [my Marketplace page](https://statamic.com/creators/jothamlec).

![The Marketing overview, with the SEO score, the failing checks, recent 404s, redirects, and the brand](https://raw.githubusercontent.com/Jotham-LEC/statamic-marketing-toolkit/main/docs/images/overview.png)

## For marketers

- **SEO Control**: Modify SEO title, description, share image, canonical address, indexing, and structured data of every page, and a preview shows how the page will look in Google and on social media as you type.
- **Sensible Defaults**: Every page gets its tags, a sitemap, robots.txt, and llms.txt without anyone filling in a field.
- **Redirects & 404s**: Anyone can add redirects, wildcards and 410s included. Moving a page adds one for you, and missing pages are logged. Bulk import from a CSV.
- **SEO Score**: Every page is checked and scored out of 100. Each problem links straight to the entry that fixes it.
- **Tracking Tags**: Paste your GTM, GA4, PostHog, Meta Pixel, or LinkedIn ID. Tags load on the live site only.
- **Integrations**: Search Console numbers per page, form submissions sent on as leads with their source, and UTM campaign links.
- **Consent Mode v2**: Works with the cookie banner you already have, with defaults per region.

## For developers

- **Statamic native**: Fieldsets, globals, forms, and tags. Your layout needs only two tags, `<s:mt:head />` and `<s:mt:body />`.
- **Lean**: No front-end script unless a feature needs one. Files are served without sessions or cookies.
- **Feature toggles**: Switch off what you don't use, and it isn't loaded at all.

How it works under the hood is in [For developers](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/developers.md).

## Screens

The preview updates as you type, for Google and for shares on Facebook, LinkedIn, WhatsApp, and X.

![An entry's SEO tab, with the Google preview and the share cards for Facebook and X](https://raw.githubusercontent.com/Jotham-LEC/statamic-marketing-toolkit/main/docs/images/entry-seo.png)

Each check lists the pages it flagged. Export the report as a CSV.

![An SEO report with a score of 98, two failing checks, and the Export CSV button](https://raw.githubusercontent.com/Jotham-LEC/statamic-marketing-toolkit/main/docs/images/report.png)

Moved pages get their redirect automatically.

![The redirects list, with a wildcard redirect, a 410, and a redirect marked Automatic](https://raw.githubusercontent.com/Jotham-LEC/statamic-marketing-toolkit/main/docs/images/redirects.png)

You're warned when GTM and another tag would count each visit twice.

![The Tracking tab of the Marketing settings, warning that Tag Manager and Google Analytics are both set](https://raw.githubusercontent.com/Jotham-LEC/statamic-marketing-toolkit/main/docs/images/tracking.png)

## Installation

```bash
composer require jotham-lec/statamic-marketing-toolkit
php artisan migrate
php please mt:install
```

Add one tag after `<meta charset>` and the viewport, and one right after `<body>`:

```blade
<s:mt:head />
<s:mt:body />
```

In Antlers, use `{{ mt:head }}` and `{{ mt:body }}`.

Entries get their SEO tab from the `marketing-toolkit::seo` fieldset. `mt:install` adds it to collections that have a route and no blueprint yet, and names the blueprints you already have that lack it, so you can link the fieldset there.

Next steps are in [Getting started](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/getting-started.md). Moving a site that already has SEO set up? Follow the [migration checklist](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/migrating.md), or hand it to an AI agent with the [migration skill](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/agent-skill/README.md).

## How it compares with other add-ons

Statamic has great add-ons for each part of this. Pick them instead if:

- **You want first-party support.** SEO Pro is by the Statamic team. This is one developer's project, supported through GitHub issues.
- **You prefer one add-on per job.** Switched-off features here cost nothing, but the add-ons below do their jobs well.
- **You prefer hand-written code.** I build this with heavy AI assistance. Every change goes through the test suite and CI on SQLite and Postgres, but you should know.

| Add-on | What it covers |
| --- | --- |
| [SEO Pro](https://statamic.com/addons/statamic/seo-pro) | SEO, sitemap, redirects, 404s, and reports, by the Statamic team |
| [Advanced SEO](https://statamic.com/addons/aerni/advanced-seo) | SEO, sitemaps, redirects, social images, and multi-site |
| [Aardvark SEO](https://statamic.com/addons/justkidding96/aardvark-seo) | On-page SEO, schema, and redirects |
| [Redirect](https://statamic.com/addons/rias/redirect) | Redirects and 404s |
| [404 Logger](https://statamic.com/addons/stoffelio/404-logger) | A log of 404 requests |
| [Consent Manager](https://statamic.com/addons/kiwikiwi/consent-manager) | A cookie banner and consent for tracking scripts |
| [Favicons](https://statamic.com/addons/kiwikiwi/favicons) | Favicons from one image |
| [AB Tester](https://statamic.com/addons/thoughtco/statamic-ab-tester) | A/B experiments |

## Requirements

- **PHP 8.3+** with `curl`, `dom`, `mbstring`, and `openssl`.
- **Statamic 6.34+**. Core is enough; multi-site needs Statamic Pro.
- **`imagick`** for share cards and favicons. Without it, `gd` draws favicons from a PNG or JPEG.
- **A database** Laravel can migrate, even on a flat-file site. SQLite is fine.
- **A serializing cache store**: `file`, `redis`, `database`, or `memcached`, not `array`.
- **Laravel's scheduler** for scheduled reports and the Search Console import. A queue worker is optional.

Details are in [Getting started](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/getting-started.md#what-you-need).

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

Free and open source under the [MIT licence](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/LICENSE). No editions, no licence key. It sends nothing to me; what it sends to other services, such as telling search engines about changed pages through IndexNow (on by default, in production), is listed in [What it sends where](https://github.com/Jotham-LEC/statamic-marketing-toolkit/blob/main/docs/configuration.md#what-it-sends-where). Questions and bugs go to [GitHub issues](https://github.com/Jotham-LEC/statamic-marketing-toolkit/issues).

If it saves you time, you can [buy me a coffee](https://buymeacoffee.com/jothamh).
