<?php

use JothamLec\Seo\Og\DefaultTemplate;
use JothamLec\Seo\SiteSeo;

return [

    /*
    |--------------------------------------------------------------------------
    | Rules class
    |--------------------------------------------------------------------------
    |
    | Every value the addon prints is worked out by one method on this class.
    | Extend JothamLec\Seo\SiteSeo in the project and override a method to
    | change one rule (an extra JSON-LD node, a noindex condition, more
    | sitemap URLs) without touching the rest.
    |
    */

    'class' => SiteSeo::class,

    /*
    |--------------------------------------------------------------------------
    | Brand and defaults
    |--------------------------------------------------------------------------
    |
    | The global set editors fill in (create it with `php please seo:install`):
    | title separator, default description and image, the publisher for
    | JSON-LD, verification codes, robots.txt lines and the OG card colours.
    | The site's name is Statamic's own (Settings → Sites, else APP_NAME).
    |
    */

    'global' => 'seo',

    'title' => [
        // Appended as "{title}{separator}{site name}" only when the result fits.
        'max' => 60,
    ],

    'description' => [
        // A description taken from the page is cut to this, on a word.
        'length' => 155,
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
    |       'currency' => null,            // else the SEO & brand global's Shop currency
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

    'robots_txt' => true,

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    |
    | Rules managed under Tools → SEO → Redirects, applied only to addresses the
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
    | One row per missing path, under Tools → SEO → 404s. Requests from these
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
        'key' => env('SEO_INDEXNOW_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Search Console
    |--------------------------------------------------------------------------
    |
    | Clicks, impressions, CTR and position per page on Tools → SEO, imported
    | daily (`php please seo:search-console`). `credentials` is a service
    | account's JSON key, or the path to it; `property` is the property as
    | Search Console names it: `sc-domain:example.com` or `https://example.com/`.
    |
    */

    'search_console' => [
        'credentials' => env('SEO_SEARCH_CONSOLE_CREDENTIALS'),
        'property' => env('SEO_SEARCH_CONSOLE_PROPERTY'),
        'days' => 28,
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
