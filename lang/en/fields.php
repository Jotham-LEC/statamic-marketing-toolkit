<?php

/*
 * The labels and help of the addon's fields: the SEO fieldset on entries and
 * terms and the "SEO & brand"
 * global set that `php please seo:install` creates. Those blueprints store
 * these keys, so each user sees them in their control panel language.
 */
return [

    // resources/fieldsets/seo.yaml
    'seo' => [
        'seo_preview' => [
            'title' => 'SEO preview',
            'display' => 'Search and share preview',
            'instructions' => 'How this page shows in Google and when shared, with the defaults filled in. Updates as you type.',
        ],
        'seo' => [
            'display' => 'SEO',
            'instructions' => 'Leave any field empty to use the default.',
        ],
        'title' => [
            'display' => 'Title',
            'instructions' => 'Replaces the whole `<title>`. Empty: "{Title} · {Site}".',
        ],
        'description' => [
            'display' => 'Description',
            'instructions' => 'Empty: the entry\'s description, else its first paragraph.',
        ],
        'image' => [
            'display' => 'Share image',
            'instructions' => 'Replaces the generated card. Cropped to 1200×630.',
        ],
        'og_title' => [
            'display' => 'Card title',
            'instructions' => 'Text on the generated card. Empty: the title.',
        ],
        'og_subtitle' => [
            'display' => 'Card subtitle',
            'instructions' => 'Empty: the description.',
        ],
        'canonical' => [
            'display' => 'Canonical URL',
            'instructions' => 'Only for a piece first published elsewhere: the original\'s address.',
        ],
        'noindex' => [
            'display' => 'Hide from search engines',
        ],
        'nofollow' => [
            'display' => 'Do not follow links',
        ],
        'nosnippet' => [
            'display' => 'No snippet',
            'instructions' => 'Show no text from this page in results, nor in AI Overviews and AI Mode.',
        ],
        'max_snippet' => [
            'display' => 'Snippet length',
            'instructions' => 'At most this many characters quoted. Empty: no limit.',
        ],
        'sitemap' => [
            'display' => 'In sitemap',
        ],
        'json_ld' => [
            'display' => 'Extra JSON-LD',
            'instructions' => 'A JSON object or array of objects, added to the page\'s @graph.',
        ],
    ],

    // The lead source fields `seo:install --forms` adds to each form (Pro).
    'attribution' => [
        'tab' => 'Lead source',
        'utm_source' => 'Source (utm_source)',
        'utm_medium' => 'Medium (utm_medium)',
        'utm_campaign' => 'Campaign (utm_campaign)',
        'utm_term' => 'Term (utm_term)',
        'utm_content' => 'Content (utm_content)',
        'referrer' => 'Came from',
        'landing_page' => 'First page',
    ],

    // The "SEO & brand" global set (src/Commands/Install.php)
    'brand' => [
        'tabs' => [
            'brand' => 'Brand',
            'publisher' => 'Publisher',
            'tracking' => 'Tracking',
            'shop' => 'Shop',
            'share_cards' => 'Share cards',
            'crawlers' => 'Crawlers',
        ],
        'sections' => [
            'icon' => [
                'display' => 'Icon',
                'instructions' => 'The site’s icon in browser tabs, bookmarks and phone home screens. Every size is made from this one image.',
            ],
            'tracking' => [
                'instructions' => 'Paste each tool’s ID; leave the others empty. They load on the live site only, never while editing. An ID your developer set in .env wins over the one here.',
            ],
            'conversions' => [
                'display' => 'Leads (Pro)',
                'instructions' => 'A form sent on the site counts as a lead in your tools: generate_lead in Google Tag Manager and Analytics, Lead for Meta, “form submitted” in PostHog, and your LinkedIn conversion.',
            ],
            'consent' => [
                'display' => 'Consent Mode',
                'instructions' => 'For a cookie banner you already have (Cookiebot, CookieYes, Iubenda…): what Google’s tags may do before a visitor answers. The banner then tells them the answer.',
            ],
            'publisher' => [
                'instructions' => 'Who is behind the site, for search engines (JSON-LD). Each value is printed only where its type accepts it.',
            ],
            'address' => [
                'display' => 'Address',
                'instructions' => 'Required for a local business with premises; leave empty for one that only serves an area.',
            ],
            'local_business' => [
                'display' => 'Local business',
                'instructions' => 'For a Store, a Restaurant or another LocalBusiness type.',
            ],
            'shop' => [
                'instructions' => 'For a site that sells: the currency of its prices, and the return and shipping policies for all its products.',
            ],
            'returns' => [
                'display' => 'Returns',
            ],
            'shipping' => [
                'display' => 'Shipping',
            ],
            'share_cards' => [
                'instructions' => 'Colours and picture for generated share images.',
            ],
        ],

        // Brand
        'favicon' => [
            'display' => 'Icon',
            'instructions' => 'A square image, 512 × 512 pixels or more: PNG, or SVG (which stays sharp at any size).',
        ],
        'theme_color' => [
            'display' => 'Theme colour',
            'instructions' => 'Phones tint the browser’s bar with it.',
        ],
        'background_color' => [
            'display' => 'Icon background',
            'instructions' => 'Behind the icon on an iPhone home screen. White unless set.',
        ],
        'conversions' => [
            'display' => 'Send form submissions as leads',
            'instructions' => 'For every form on the site.',
        ],
        'linkedin_conversion_id' => ['display' => 'LinkedIn conversion ID'],
        'attribution' => [
            'display' => 'Save where each lead came from',
            'instructions' => 'The campaign (UTM tags), the site that sent them and the page they landed on, with each form submission. It keeps them in a cookie: in the EU and UK, use Consent Mode too. Your developer adds the fields to the forms once.',
        ],
        'tracking_overlap' => [
            'display' => 'Move these tags into Google Tag Manager',
            'instructions' => 'Google Tag Manager is set, and so is at least one other tool here. If GTM loads that tool too, every visit counts twice. Add each tool as a tag in GTM, then clear its ID here.',
        ],
        'gtm_id' => [
            'display' => 'Google Tag Manager',
            'instructions' => 'With GTM, add every other tool as a tag there rather than here.',
        ],
        'ga4_id' => ['display' => 'Google Analytics 4 measurement ID'],
        'posthog_key' => ['display' => 'PostHog project API key'],
        'posthog_host' => [
            'display' => 'PostHog host',
            'instructions' => 'https://eu.i.posthog.com for an EU project; US unless set.',
        ],
        'meta_pixel_id' => ['display' => 'Meta Pixel ID'],
        'linkedin_partner_id' => ['display' => 'LinkedIn Insight Tag partner ID'],
        'consent_mode' => [
            'display' => 'Use Consent Mode',
            'instructions' => 'Off: the tags load as soon as the page does.',
        ],
        'consent_ad_storage' => ['display' => 'Advertising cookies (ad_storage)'],
        'consent_analytics_storage' => ['display' => 'Analytics cookies (analytics_storage)'],
        'consent_ad_user_data' => ['display' => 'Send data to Google for ads (ad_user_data)'],
        'consent_ad_personalization' => ['display' => 'Personalised ads (ad_personalization)'],
        'consent_value' => [
            'options' => [
                'denied' => 'Denied until they agree',
                'granted' => 'Granted',
            ],
        ],
        'consent_wait_for_update' => [
            'display' => 'Wait for the banner',
            'instructions' => 'How long Google’s tags wait for the banner’s answer before using these defaults.',
        ],
        'consent_regions' => [
            'display' => 'Only in these regions (Pro)',
            'instructions' => 'Country codes (FR, US-CA). The defaults above apply there; everywhere else, everything is granted. Empty: everywhere.',
            'options' => [
                'eea' => 'EEA, UK and Switzerland',
            ],
        ],
        'title_separator' => [
            'display' => 'Title separator',
            'instructions' => 'Between the page title and the site name, with a space on each side.',
        ],
        'default_description' => [
            'display' => 'Default description',
            'instructions' => 'For pages with no description and no first paragraph.',
        ],
        'default_image' => [
            'display' => 'Default share image',
            'instructions' => 'For pages without an image or a generated card. 1200×630.',
        ],
        'site_alternate_name' => [
            'display' => 'Other site name',
            'instructions' => 'A shorter name or acronym search engines may show instead.',
        ],
        'twitter_handle' => ['display' => 'X handle'],

        // Publisher
        'publisher_type' => [
            'display' => 'Type',
            'instructions' => 'The most specific schema.org type, or two (EducationalOrganization and LocalBusiness). Type any other schema.org type.',
            'options' => [
                'organization' => 'Organization',
                'corporation' => 'Corporation',
                'educational_organization' => 'Educational organization',
                'ngo' => 'Non-profit',
                'local_business' => 'Local business',
                'store' => 'Store',
                'professional_service' => 'Professional service',
                'restaurant' => 'Restaurant',
                'person' => 'Person',
            ],
        ],
        'publisher_name' => ['display' => 'Name'],
        'publisher_alternate_name' => [
            'display' => 'Other name',
            'instructions' => 'An abbreviation or former name.',
        ],
        'founding_date' => ['display' => 'Founded'],
        'publisher_description' => ['display' => 'Description'],
        'publisher_logo' => ['display' => 'Logo or portrait'],
        'job_title' => ['display' => 'Job title'],
        'telephone' => ['display' => 'Telephone'],
        'email' => ['display' => 'Email'],
        'area_served' => ['display' => 'Area served'],
        'same_as' => [
            'display' => 'Profiles elsewhere',
            'instructions' => 'Full URLs: LinkedIn, Instagram, Google Business Profile…',
        ],
        'contact_points' => [
            'display' => 'Contact points',
            'add_row' => 'Add a contact point',
        ],
        'contact_type' => [
            'display' => 'For',
            'placeholder' => 'customer service',
        ],
        'street_address' => ['display' => 'Street address'],
        'address_locality' => ['display' => 'City'],
        'address_region' => ['display' => 'State or region'],
        'postal_code' => ['display' => 'Postcode'],
        'address_country' => ['display' => 'Country code'],
        'price_range' => ['display' => 'Price range'],
        'latitude' => ['display' => 'Latitude'],
        'longitude' => ['display' => 'Longitude'],
        'opening_hours' => [
            'display' => 'Opening hours',
            'add_row' => 'Add hours',
        ],
        'days' => [
            'display' => 'Days',
            'options' => [
                'monday' => 'Mon',
                'tuesday' => 'Tue',
                'wednesday' => 'Wed',
                'thursday' => 'Thu',
                'friday' => 'Fri',
                'saturday' => 'Sat',
                'sunday' => 'Sun',
            ],
        ],
        'opens' => ['display' => 'Opens'],
        'closes' => ['display' => 'Closes'],

        // Shop
        'currency' => [
            'display' => 'Currency',
            'instructions' => 'Three-letter code.',
        ],
        'return_category' => [
            'display' => 'Returns',
            'options' => [
                'finite_window' => 'Within a number of days',
                'unlimited_window' => 'Any time',
                'not_permitted' => 'Not accepted',
            ],
        ],
        'return_days' => ['display' => 'Days to return'],
        'return_country' => ['display' => 'Country code'],
        'return_policy_link' => [
            'display' => 'Return policy page',
            'instructions' => 'Enough on its own, or alongside the details above.',
        ],
        'shipping_rates' => [
            'display' => 'Shipping rates',
            'add_row' => 'Add a rate',
            'instructions' => 'One row per destination and order value. Leave the order values empty for a flat rate.',
        ],
        'country' => ['display' => 'Country'],
        'region' => ['display' => 'Region'],
        'min_order' => ['display' => 'Orders from'],
        'max_order' => ['display' => 'Orders up to'],
        'rate' => ['display' => 'Rate'],
        'min_days' => ['display' => 'Days, from'],
        'max_days' => ['display' => 'Days, to'],

        // Share cards
        'og_background' => ['display' => 'Background'],
        'og_text' => ['display' => 'Text'],
        'og_accent' => ['display' => 'Accent'],
        'og_picture' => [
            'display' => 'Picture',
            'instructions' => 'A logo or portrait on every card.',
        ],

        // Crawlers
        'google_verification' => ['display' => 'Google verification'],
        'bing_verification' => ['display' => 'Bing verification'],
        'yandex_verification' => ['display' => 'Yandex verification'],
        'pinterest_verification' => ['display' => 'Pinterest verification'],
        'robots_disallow' => [
            'display' => 'robots.txt Disallow',
            'instructions' => 'Paths to keep crawlers out of. Empty: the control panel.',
        ],
        'allow_ai_training' => [
            'display' => 'Allow AI training',
            'instructions' => 'Off: GPTBot, ClaudeBot, Google-Extended, Applebot-Extended and CCBot are turned away in robots.txt. Google Search is unaffected.',
        ],
        'allow_ai_search' => [
            'display' => 'Allow AI search',
            'instructions' => 'Off: the crawlers behind ChatGPT search, Claude and Perplexity answers are turned away.',
        ],
        'robots_extra' => [
            'display' => 'robots.txt extra lines',
            'instructions' => 'Added as typed, e.g. rules for AI crawlers.',
        ],
    ],

];
