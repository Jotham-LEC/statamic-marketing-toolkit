# Configuration

Two places, by who changes what:

- **`config/seo.php`**: rules that belong in code and git (which field is the description, the schema type, redirects and the 404 log). Publish it with `php artisan vendor:publish --tag=seo-config`; anything you leave out keeps its default.
- **Tools → Addons → SEO** (Statamic's addon settings): report settings an editor may want to change.

Brand details (site name, logo, colours, verification codes) are content, edited under **Globals → SEO & brand**; see [editors.md](editors.md#seo--brand).

## config/seo.php

### Rules class

| Key | Default | |
|---|---|---|
| `class` | `JothamLec\Seo\SiteSeo` | The class that works out every value. Extend it to change one rule; see [developers.md](developers.md#change-a-rule-siteseo). |
| `global` | `'seo'` | Handle of the brand global set. |

### Titles and descriptions

| Key | Default | |
|---|---|---|
| `title.max` | `60` | `{title}{separator}{site name}` is used only if it fits; otherwise the title alone. |
| `description.length` | `155` | A description taken from the page is cut to this, on a word. |
| `description.skip_prefixes` | `[]` | A first paragraph starting with one of these isn't used as the description (e.g. `'This article first appeared'`). |

### Collections

`collections` maps a collection handle to its rules. Every key is optional.

```php
'collections' => [
    'essays' => [
        'og_type' => 'article',            // og:type (default 'website')
        'schema' => 'Article',             // adds an Article, NewsArticle or BlogPosting node
        'page_schema' => 'WebPage',        // the WebPage node's type (CollectionPage, ProfilePage…)
        'description_fields' => ['intro'], // tried before the body's first paragraph
        'image_fields' => ['hero'],        // tried before the generated card
        'faq_field' => 'faqs',             // a grid of question / answer rows → FAQPage
        'og_template' => 'default',        // a key of og.templates
    ],
],
```

### Robots

| Key | Default | |
|---|---|---|
| `robots.noindex_outside_production` | `true` | Every page is `noindex` unless `APP_ENV=production`, so staging copies stay out of search results. |
| `robots.noindex_params` | `[]` | A request with any of these query parameters is noindexed (filtered or sorted listings), e.g. `['sort', 'tag']`. |
| `robots.noindex_routes` | `[]` | Route names to noindex, e.g. `['thank-you']`. |
| `robots.default` | `'max-snippet:-1, max-image-preview:large, max-video-preview:-1'` | The robots tag on pages that are indexed. |

### Sitemap, robots.txt, humans.txt

| Key | Default | |
|---|---|---|
| `sitemap.enabled` | `true` | Serves `/sitemap.xml`. |
| `sitemap.collections` | `null` | `null`: every collection with a route. Or a list of handles. |
| `sitemap.exclude_collections` | `[]` | Left out even when `collections` is `null`. |
| `sitemap.taxonomies` | `[]` | Taxonomies whose terms are listed (only terms with published entries). Reports check these terms too. |
| `sitemap.per_page` | `1000` | Above this, `/sitemap.xml` becomes an index of `/sitemap_1.xml`, `/sitemap_2.xml`… |
| `robots_txt` | `true` | Serves `/robots.txt` from the global. A real `public/robots.txt` wins. |
| `humans_txt` | `true` | Serves `/humans.txt` when the global's humans.txt field is filled in. |

The sitemap leaves out drafts, redirect entries, noindexed pages, pages whose canonical points to another site, and pages with "In sitemap" off. It's cached and rebuilt when content is saved or deleted, and when a scheduled entry's date arrives (that needs Laravel's scheduler running, as Statamic's scheduled entries do).

### Redirects and the 404 log

| Key | Default | |
|---|---|---|
| `redirects.enabled` | `true` | Applies the rules under Tools → SEO → Redirects. |
| `redirects.automatic` | `true` | Adds a 301 when published content moves (slug, date, place in a tree). |
| `not_found.enabled` | `true` | Logs 404s. |
| `not_found.max_rows` | `1000` | Most paths kept; the least recently seen go first. |
| `not_found.ignore_user_agents` | bots, crawlers, curl, wget… | Not logged when the user agent contains one of these (any case). |
| `not_found.ignore_paths` | `*.php`, `/wp-*`, `/.env*`, `/.git*`… | Not logged when the path matches one (`*` matches anything). Scanner probes, mostly. |

### Trailing slash

| Key | Default | |
|---|---|---|
| `trailing_slash` | `null` | `'add'` or `'remove'` sends a 301 to the other form for GET and HEAD requests (not for the control panel, files or `/img`), and redirect targets get the same form. `null` leaves URLs alone. |

### Share cards and images

| Key | Default | |
|---|---|---|
| `og.enabled` | `true` | Generated cards at `/og.png` (home) and `/og/{uri}.png`. |
| `og.templates` | `['default' => DefaultTemplate::class]` | Card designs by key; see [developers.md](developers.md#add-a-share-card-template). |
| `og.cache_store` | `null` | Laravel cache store for drawn cards; `null` uses the default. |
| `og.max_age` | 30 days | `Cache-Control` max-age of the card images. |
| `image.width`, `image.height` | `1200`, `630` | Uploaded share images are cropped to this and served as JPEG. |

## Addon settings: Tools → Addons → SEO

Saved as YAML in `resources/addons/seo.yaml` (or wherever your site stores addon settings). On a site where the production control panel is where content lives, keep that file out of deploys, or store addon settings in the database, so a deploy doesn't overwrite them.

**Checks** tab:

| Setting | Default | |
|---|---|---|
| A switch per check | all on | A check that's off is left out of the reports and the scores. |
| Title: at least / at most | 30 / 60 | Thresholds for the title check and the preview's title counter. |
| Description: at least / at most | 50 / 160 | The same for descriptions. |

**Running** tab:

| Setting | Default | |
|---|---|---|
| Leave out these collections | none | Their entries aren't checked. |
| Most pages per report | 0 (all) | Stops after this many pages. |
| Pages per step | 25 | Pages one step renders. Lower it if a step times out. |
| Reports to keep | 10 | Older reports are deleted when a new one finishes. |
| Run a report | Only by hand | Or daily or weekly, on the day and at the time you choose (app timezone). Needs the scheduler. |

## Permissions

| Permission | |
|---|---|
| `view seo` | Tools → SEO, reports, the 404 log, the widget. |
| `manage seo redirects` | Create, edit, import, export and delete redirects; delete 404 rows; the "add a redirect?" question when saving. |
| `run seo reports` | Start a report. |

## Commands

| Command | |
|---|---|
| `php please seo:install [--container=]` | Creates the SEO & brand global set and its blueprint. |
| `php please seo:report` | Runs a whole report in the terminal and prints the scores. Continues a report that's already running. |
