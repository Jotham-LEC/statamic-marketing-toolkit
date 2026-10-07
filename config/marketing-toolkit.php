<?php

use JothamLec\MarketingToolkit\Og\DefaultTemplate;

return [

    /*
    |--------------------------------------------------------------------------
    | Brand and defaults
    |--------------------------------------------------------------------------
    |
    | The two global sets editors fill in, each with its own values per site
    | (create them with `php please mt:install`). `global` is Brand: the title
    | separator, default description and image, the publisher for JSON-LD, the
    | shop and the share-card colours. `settings_global` is Marketing settings:
    | tracking tags, Consent Mode, leads, verification codes and robots.txt.
    | The site's name is Statamic's own (Settings → Sites, else APP_NAME).
    |
    */

    'global' => 'seo',

    'settings_global' => 'marketing',

    'title' => [
        // With "Add the site name to page titles" on (Brand), the title
        // becomes "{title}{separator}{site name}" only when the result fits.
        // The report's title check uses its own limit; the defaults match.
        'max' => 60,
    ],

    'description' => [
        // A description taken from the page is cut to this, on a word. The
        // report's checks and the preview's counters use their own limits
        // (Marketing → Reports → Settings); the defaults match.
        'length' => 160,
    ],

    /*
    |--------------------------------------------------------------------------
    | Collections
    |--------------------------------------------------------------------------
    |
    | Per collection handle, all keys optional:
    |
    |   'og_type' => 'article',            // og:type; default 'website'
    |   'schema' => 'Article',             // adds an Article / NewsArticle / BlogPosting node
    |   'page_schema' => 'WebPage',        // the WebPage node's type (CollectionPage, ProfilePage: about the entry, as a Person)
    |   'description_fields' => ['intro'], // tried before the body's first paragraph
    |   'image_fields' => ['hero'],        // tried before the generated card
    |   'faq_field' => 'faqs',             // a grid of question / answer → FAQPage (valid markup; Google shows no FAQ results since 2026)
    |   'author_field' => 'authors',       // an entries or users field → the Article's authors (else the publisher)
    |   'product' => [                     // a Product + Offer from these fields (needs a price above 0 and a currency)
    |       'price_field' => 'price', 'availability_field' => 'in_stock', // a toggle, or InStock/PreOrder…
    |       'sku_field' => 'sku', 'gtin_field' => null, 'brand_field' => null, 'brand' => 'Acme',
    |       'currency' => null,            // else the Brand global's Shop currency
    |       'condition' => 'NewCondition',
    |   ],
    |   'og_template' => 'default',        // a key of og.templates
    |
    | description_fields, image_fields and faq_field can name a field in a
    | Replicator's sets as 'replicator.set.field' ('sections.hero.image'; `*`
    | for any set); sets switched off are passed over.
    |
    */

    'collections' => [],

    /*
    |--------------------------------------------------------------------------
    | Taxonomies
    |--------------------------------------------------------------------------
    |
    | The same rules for a taxonomy's term pages, by taxonomy handle: og_type,
    | page_schema (CollectionPage suits a listing), description_fields,
    | image_fields and faq_field.
    |
    */

    'taxonomies' => [],

    /*
    |--------------------------------------------------------------------------
    | Robots
    |--------------------------------------------------------------------------
    */

    'robots' => [
        // Keep staging and local copies out of search results.
        'noindex_outside_production' => true,

        // A request carrying any of these query parameters is noindexed
        // (filtered or sorted listings), e.g. ['sort', 'search', 'tag'].
        'noindex_params' => [],

        // Route names to noindex, e.g. ['thank-you'].
        'noindex_routes' => [],

        'default' => 'max-snippet:-1, max-image-preview:large, max-video-preview:-1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap and robots.txt
    |--------------------------------------------------------------------------
    */

    'sitemap' => [
        'enabled' => true,

        // null: every collection that has a route.
        'collections' => null,

        'exclude_collections' => [],

        // Taxonomies whose terms are listed (only terms with published entries).
        'taxonomies' => [],

        // Above this many URLs, /sitemap.xml becomes an index of /sitemap_{n}.xml.
        'per_page' => 1000,
    ],

    'robots_txt' => [
        'enabled' => true,
    ],

    // /llms.txt: the site's pages as a Markdown list for AI assistants (llmstxt.org).
    'llms_txt' => [
        'enabled' => true,
    ],

    // /ads.txt: the lines in Marketing settings → Crawlers, when there are any.
    'ads_txt' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Languages (hreflang)
    |--------------------------------------------------------------------------
    |
    | With several sites, a page links to itself in each other language its
    | entry (or term) is published in: <link rel="alternate" hreflang> tags
    | and the same in the sitemap. The code is the site's language (`fr`),
    | or its full locale (`en-GB`) where two sites share a language.
    | `x_default` names the site whose version everyone else gets: null for
    | the default site, a site handle, or false for none.
    |
    */

    'hreflang' => [
        'enabled' => true,
        'x_default' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    |
    | Rules managed under Marketing → Redirects, applied only to addresses the
    | site would answer with a 404. `automatic` adds a 301 when an entry's or
    | a term's address changes (its slug, its date, its place in a tree).
    | `case_sensitive` false matches a rule's From in any letter case
    | (`/ABOUT-US` as `/about-us`), for a site moved off one whose addresses
    | worked in any case (Wix, IIS).
    |
    */

    'redirects' => [
        'enabled' => true,

        'automatic' => true,

        'case_sensitive' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 404 log
    |--------------------------------------------------------------------------
    |
    | One row per missing path, under Marketing → 404s. Requests from these
    | user agents (matched case-insensitively, anywhere in the string) and to
    | these paths (`*` matches anything) are not logged.
    |
    */

    'not_found' => [
        'enabled' => true,

        'max_rows' => 1000,

        'ignore_user_agents' => [
            'bot', 'crawler', 'spider', 'slurp', 'curl', 'wget', 'python-requests',
            'go-http-client', 'headlesschrome', 'lighthouse', 'facebookexternalhit',
        ],

        'ignore_paths' => [
            '*.php', '*.asp', '*.aspx', '*.cgi', '/wp-*', '/wordpress*', '/.env*',
            '/.git*', '/.well-known/*', '/cgi-bin/*', '/vendor/*', '/xmlrpc*',
            '*.map',
            // What browsers and crawlers ask for on their own: at max_rows they
            // would push the broken links out.
            '/favicon.ico', '/robots.txt', '/sitemap.xml', '/apple-touch-icon*', '/build/*',
            '*.js', '*.css', '*.png', '*.jpg', '*.jpeg', '*.gif', '*.svg', '*.webp', '*.ico',
            '*.woff', '*.woff2', '*.ttf', '*.eot',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IndexNow
    |--------------------------------------------------------------------------
    |
    | Tells Bing, Yandex and the other IndexNow engines (not Google) which
    | addresses changed when published content is saved or deleted, in
    | production only. The key is served at /{key}.txt; left null, it is
    | derived from APP_KEY.
    |
    */

    'indexnow' => [
        'enabled' => true,
        'key' => env('MT_INDEXNOW_KEY', env('SEO_INDEXNOW_KEY')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Search Console
    |--------------------------------------------------------------------------
    |
    | Clicks, impressions, CTR and position per page on Marketing → Overview, imported
    | daily (`php please mt:search-console`). `credentials` is a service
    | account's JSON key, or the path to it; `property` is the property as
    | Search Console names it: `sc-domain:example.com` or `https://example.com/`.
    |
    */

    'search_console' => [
        'credentials' => env('MT_SEARCH_CONSOLE_CREDENTIALS', env('SEO_SEARCH_CONSOLE_CREDENTIALS')),
        'property' => env('MT_SEARCH_CONSOLE_PROPERTY', env('SEO_SEARCH_CONSOLE_PROPERTY')),
        'days' => 28,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking
    |--------------------------------------------------------------------------
    |
    | Google Tag Manager, Google Analytics 4, PostHog, the Meta Pixel and the
    | LinkedIn Insight Tag, printed by <s:mt:head /> and <s:mt:body />. Set
    | them in the Tracking tab of Marketing settings, or here (.env),
    | which wins. They print only in these environments, never in Live Preview.
    |
    */

    'tracking' => [
        'enabled' => true,
        'environments' => ['production'],
        // Each key is the field's handle in the Tracking tab, and MT_ + the key in .env.
        // (SEO_ + the key, the name up to 0.19, is read too until 1.0.)
        'gtm_id' => env('MT_GTM_ID', env('SEO_GTM_ID')),
        'ga4_id' => env('MT_GA4_ID', env('SEO_GA4_ID')),
        'posthog_key' => env('MT_POSTHOG_KEY', env('SEO_POSTHOG_KEY')),
        'posthog_host' => env('MT_POSTHOG_HOST', env('SEO_POSTHOG_HOST')),
        'meta_pixel_id' => env('MT_META_PIXEL_ID', env('SEO_META_PIXEL_ID')),
        'linkedin_partner_id' => env('MT_LINKEDIN_PARTNER_ID', env('SEO_LINKEDIN_PARTNER_ID')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Leads
    |--------------------------------------------------------------------------
    |
    | Form submissions sent to your tools as leads, and where each lead came
    | from saved with its submission, as set in the Leads tab of Marketing
    | settings. Off here (or under Features), neither happens.
    |
    */

    'leads' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Favicons
    |--------------------------------------------------------------------------
    |
    | /favicon.ico, /favicon.svg, /apple-touch-icon.png, /icon-192.png,
    | /icon-512.png and /site.webmanifest, made from the icon in Brand,
    | and their <link> tags in <s:mt:head />. A file of the same name in
    | public/ wins.
    |
    */

    'favicons' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    |
    | Which checks a report runs, their thresholds and its schedule are set
    | under Marketing → Reports → Settings. Off here (or under Features), no
    | report runs on the schedule; one can still run by hand.
    |
    */

    'reports' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Generated Open Graph images
    |--------------------------------------------------------------------------
    |
    | A page without an uploaded share image gets a card drawn at
    | /og/{uri}.png (home: /og.png) by simonhamp/the-og. Editors change the
    | card's text per entry in the SEO fieldset, or replace it outright with
    | an uploaded image; a collection picks a template with `og_template`.
    |
    */

    'og' => [
        'enabled' => true,

        'templates' => [
            'default' => DefaultTemplate::class,
        ],

        'max_age' => 60 * 60 * 24 * 30,
    ],

];
