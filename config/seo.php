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
    | site name, default description and image, the publisher for JSON-LD,
    | verification codes, robots.txt lines, humans.txt and the OG card colours.
    |
    */

    'global' => 'seo',

    'title' => [
        // Appended as "{title}{separator}{site name}" only when the result fits.
        'max' => 60,

        // Shorter than this is flagged in the CP preview.
        'min' => 30,
    ],

    'description' => [
        'length' => 155,

        // Shorter than this is flagged in the CP preview.
        'min' => 50,

        // A first paragraph starting with one of these is skipped when the
        // description falls back to the body (e.g. "This article first appeared").
        'skip_prefixes' => [],
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
    |   'page_schema' => 'WebPage',        // the WebPage node's type (CollectionPage, ProfilePage…)
    |   'description_fields' => ['intro'], // tried before the body's first paragraph
    |   'image_fields' => ['hero'],        // tried before the generated card
    |   'faq_field' => 'faqs',             // a grid of question / answer → FAQPage
    |   'og_template' => 'default',        // a key of og.templates
    |
    */

    'collections' => [],

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
    | Sitemap, robots.txt and humans.txt
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

    'humans_txt' => true,

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    |
    | Rules managed under Tools → SEO → Redirects, applied only to addresses the
    | site would answer with a 404. `automatic` adds a 301 when an entry's or
    | a term's address changes (its slug, its date, its place in a tree).
    |
    */

    'redirects' => [
        'enabled' => true,

        'automatic' => true,
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
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trailing slash
    |--------------------------------------------------------------------------
    |
    | null leaves URLs alone; 'add' or 'remove' sends a 301 to the canonical
    | form for GET and HEAD requests on Statamic's front-end routes.
    |
    */

    'trailing_slash' => null,

    /*
    |--------------------------------------------------------------------------
    | Generated Open Graph images
    |--------------------------------------------------------------------------
    |
    | A page without an uploaded share image gets a card drawn at
    | /og/{uri}.png (home: /og.png) by simonhamp/the-og. Editors override the
    | card's text or template per entry in the SEO fieldset, or replace it
    | outright with an uploaded image.
    |
    */

    'og' => [
        'enabled' => true,

        'templates' => [
            'default' => DefaultTemplate::class,
        ],

        // Laravel cache store for drawn cards; null uses the default store.
        'cache_store' => null,

        'max_age' => 60 * 60 * 24 * 30,
    ],

    // Uploaded share images are cropped to this size and served as JPEG.
    'image' => [
        'width' => 1200,
        'height' => 630,
    ],

];
