# Marketing Toolkit

The marketing fundamentals for a Statamic website, in one addon: SEO with a score, redirects and 404s, your tracking tags, Google Consent Mode, leads from your forms, and the site's icons. For web developers and marketing teams who know what Yoast, Rank Math and Redirection do on WordPress, and want the same on Statamic, without the bloat.

![The SEO score on Tools → SEO](https://raw.githubusercontent.com/Jotham-LEC/statamic-marketing-toolkit/main/docs/images/overview.png)

## For marketers

- **Granular control over SEO**: title, description, share image, canonical, noindex and structured data for every page, with a live Google and social preview as you type.
- **Sensible defaults**: install and go. Every page gets its tags, a sitemap, robots.txt and llms.txt without anyone filling in a field.
- **404s and redirects**: redirects anyone can manage, wildcards included; *(Pro)* a redirect added when a page moves, the list of missing pages, and a spreadsheet import.
- **A score and a report** *(Pro)*: every page checked and scored out of 100 (titles, descriptions, headings, broken links, share images and more), on a gauge, with each issue one click from its fix.
- **Your tracking tags**: Google Tag Manager, Google Analytics 4, PostHog, the Meta Pixel and LinkedIn, pasted into the control panel, on the live site only.
- **Integrations** *(Pro)*: Google Search Console numbers per page, every form sent to your tools as a lead with where it came from, and campaign links with UTM tags.
- **Consent** *(Pro)*: Google Consent Mode v2 for the cookie banner you already have, by region.

## For developers

- **Statamic native**: fieldsets, globals, forms, tags and the control panel, extended through Statamic's own APIs. Two tags in the layout: `<s:seo:head />` and `<s:seo:body />`.
- **Performance first**: no front-end script unless a feature that needs one is on, files served without sessions or cookies, and caches that clear when content changes.
- **Batteries included, no bloat**: SEO, redirects, tracking, favicons and leads in one package, with one set of brand settings.
- **Feature toggles** *(Pro)*: switch off what a site doesn't use, and it isn't loaded at all.
- **Overridable rules**: every value comes from a class you can extend.

## Free and Pro

The free edition is free on any site. Pro is **$75 per site, a perpetual licence**, bought on the [Statamic Marketplace](https://statamic.com/addons/jothamlec/marketing-toolkit) and set in `config/statamic/editions.php`. Local and staging sites don't need a licence. The free edition shows Pro's features in the control panel as cards you can upgrade from; nothing you set up is lost when you switch. See [the editions](docs/getting-started.md#the-editions) for the full list.

## Documentation

[Getting started](docs/getting-started.md) · [For editors](docs/editors.md) · [Tracking and Consent Mode](docs/tracking.md) · [Configuration](docs/configuration.md) · [For developers](docs/developers.md) · [Troubleshooting](docs/troubleshooting.md) · [Upgrading from Co-SEO](docs/upgrading.md) · [Changelog](CHANGELOG.md)

## Licence and support

Marketing Toolkit is a commercial addon by CoThinking. All rights reserved. Questions and bug reports: [GitHub issues](https://github.com/Jotham-LEC/statamic-marketing-toolkit/issues).
