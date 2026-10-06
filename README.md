# Marketing Toolkit: SEO that tells you what to fix

Marketing Toolkit puts Google's numbers next to a check of every page on your Statamic site, so your marketing team can see what to fix first, and why it matters, without leaving the control panel.

It is the only Statamic SEO addon that shows Google Search Console's clicks and positions beside a site audit. It also draws share cards for every page, on any host, with no headless browser to install.

## See what Google sees

<!-- Screenshot: docs/images/search-console.png (Tools → SEO, Google Search panel) -->

Connect Google Search Console once, step by step, from the control panel. Every day Marketing Toolkit brings in how often each page appeared in Google, how often it was clicked, and where it ranked. It sits next to your site's report, so a page that ranks but never gets clicked, or one that has dropped, stands out.

## Fix what's broken

<!-- Screenshot: docs/images/report.png (a report with its score) -->

A report reads every page the way a search engine does and gives the site a score out of 100. It lists what to fix, most important first:

- missing or overlong titles and descriptions, and ones used on more than one page
- broken links, inside the site and out
- pages that no other page links to
- images with no description, pages with no share image
- pages hidden from Google that are still in the sitemap

Reports run when you ask, or every day or week. Each issue links straight to the page's edit screen.

## Share cards that look right everywhere

<!-- Screenshot: docs/images/share-card.png (a generated card) -->

When a page is shared on LinkedIn, WhatsApp, Slack or X, it shows a picture. Marketing Toolkit draws one for every page that has none, in your brand's colours, with the page's title. Editors can change its wording, or upload their own image. It works on any host, including shared hosting, because it needs no headless browser.

## For your team

- **A live Google preview on every page.** Editors see the search result and the share card as they type, with counters that turn amber when a title or description is too short or too long.
- **Nothing gets lost when a page moves.** Change a page's address and Marketing Toolkit asks whether to send the old one to the new one, then does it.
- **Redirects anyone can manage.** Add them one by one or import a spreadsheet; send a whole section with a wildcard, or tell Google a page is gone for good.
- **A list of missing pages.** See the addresses visitors and search engines ask for that don't exist, and redirect them in a click.
- **Sensible defaults.** Every page gets a title, a description, a canonical address, Open Graph and X tags, and structured data for Google, without anyone filling in a field. Editors change only what they want to.

## Several languages

- **hreflang, automatically.** Each page tells Google where it is in every other language, so French visitors get the French page. The sitemap carries the same links.
- **A title per language.** Every SEO field can differ from one language to another.
- **A sitemap per domain**, whether languages live under `/fr/` or on their own domains.
- **A control panel in your language.** Every word in Marketing Toolkit's screens can be translated.

## Free and Pro

| | Free | Pro |
|---|:---:|:---:|
| Titles, descriptions, Open Graph and X cards | ✓ | ✓ |
| Structured data (JSON-LD) for Google | ✓ | ✓ |
| Sitemap and robots.txt | ✓ | ✓ |
| Live Google and share preview, with counters | ✓ | ✓ |
| Redirects, with wildcards and "gone for good" | ✓ | ✓ |
| Several sites and languages, with hreflang | ✓ | ✓ |
| Instant indexing with Bing and others (IndexNow) | ✓ | ✓ |
| Rules your developer can change in code | ✓ | ✓ |
| Google Search Console numbers per page | | ✓ |
| Site reports with a score, on a schedule | | ✓ |
| Broken link checks, inside and out | | ✓ |
| Share cards drawn for every page | | ✓ |
| Redirects added automatically when a page moves | | ✓ |
| The list of missing pages (404s) | | ✓ |
| Import and export redirects as a spreadsheet | | ✓ |
| Dashboard widget | | ✓ |

The free edition shows Pro's features in the control panel as cards you can upgrade from. Nothing you set up is lost when you switch.

## Why Marketing Toolkit

- **Sitemaps, several languages and redirects are free.** Not hidden behind an upgrade.
- **Google's numbers inside your control panel**, next to what to fix.
- **No headless browser needed** for share images, so they work on any host.
- **Built for Statamic 6**, and works on Statamic Core: you don't need Statamic Pro unless you run several sites.
- **One price, no renewals** while Marketing Toolkit is below version 1.0.

## Installing

Ask your developer to run:

```bash
composer require jotham-lec/statamic-marketing-toolkit
php artisan migrate
php please seo:install
```

then fill in **Globals → SEO & brand**. For Pro, they buy it on the Marketplace and set the edition in `config/statamic/editions.php`.

Developers: start with [Getting started](docs/getting-started.md), then [For developers](docs/developers.md).

| Documentation | |
|---|---|
| [Getting started](docs/getting-started.md) | Requirements, installing, the editions, the tag, permissions, and a checklist that it works. |
| [A guide for editors](docs/editors.md) | For the people who write the pages: the fields, the preview, redirects, 404s and reports. |
| [Configuration](docs/configuration.md) | Every setting, permission and command. |
| [For developers](docs/developers.md) | How values are worked out, changing a rule in code, share-card templates, several sites. |
| [Troubleshooting](docs/troubleshooting.md) | Problems people have hit, and their fixes. |
| [Changelog](CHANGELOG.md) | What changed in each version. |

## Licence and support

Marketing Toolkit is a commercial addon, sold on the [Statamic Marketplace](https://statamic.com/addons/jothamlec/marketing-toolkit). The free edition is free on any site. Pro is $59, once, with updates included and no renewal while Marketing Toolkit is below 1.0. A live site on Pro needs a licence: buy it on the Marketplace, then add it to the site on statamic.com. Local and staging sites don't need one.

Questions and bug reports: [GitHub issues](https://github.com/Jotham-LEC/statamic-co-seo/issues).

Made by CoThinking. All rights reserved.
