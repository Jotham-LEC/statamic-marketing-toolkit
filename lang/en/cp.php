<?php

/*
 * The control panel's words. Copy this folder to lang/{locale} (or publish
 * it with `php artisan vendor:publish --tag=seo-translations`) to translate.
 */
return [

    'seo' => 'SEO',

    'nav' => [
        'reports' => 'Link check',
        'redirects' => 'Redirects',
        'not_found' => '404s',
        'search_console' => 'Search Console',
        'brand' => 'Brand & defaults',
    ],

    'permissions' => [
        'view' => 'View SEO overview, link checks and 404s',
        'view_free' => 'View SEO overview',
        'redirects' => 'Manage redirects',
        'reports' => 'Run link checks',
    ],

    'pro' => [
        'badge' => 'Pro',
        'get' => 'Get Marketing Toolkit Pro',
        'reports' => [
            'title' => 'Link check',
            'body' => 'Every week, every page opened and checked: links that lead nowhere, here or on other sites, and pages with no description or share image. Each one a click away from its edit screen.',
        ],
        'not_found' => [
            'title' => '404s',
            'body' => 'The addresses visitors and search engines ask for that don’t exist, most frequent first, each a click away from a redirect.',
        ],
        'search_console' => [
            'title' => 'Google Search',
            'body' => 'Clicks, appearances and position for each page, from Google Search Console, inside the control panel.',
        ],
        'sites' => [
            'title' => 'Several sites and languages',
            'body' => 'This install has several sites. The free edition looks after the default site: its sitemap, robots.txt and settings. Pro adds every other site, and hreflang links between languages so each visitor gets the page in their language.',
        ],
        'csv' => 'Import and export redirects as CSV with Marketing Toolkit Pro.',
    ],

    'overview' => [
        'report' => [
            'title' => 'Link check',
            'pages' => ':count page to fix,|:count pages to fix,',
            'all_good' => 'Nothing to fix,',
            'none' => 'Not checked yet. The link check opens every page and lists broken links and missing descriptions or share images.',
            'open_latest' => 'Open the latest check',
            'all' => 'All checks',
        ],
        'not_found' => [
            'title' => '404s',
            'none' => 'No missing pages logged yet.',
            'count' => ':count missing address logged. Most recent:|:count missing addresses logged. Most recent:',
            'all' => 'All 404s',
        ],
        'redirects' => [
            'title' => 'Redirects',
            'summary' => ':active active, :automatic of them added when a page moved. A redirect only applies where the site would show a 404.',
            'manage' => 'Manage redirects',
        ],
        'brand' => [
            'title' => 'Brand and defaults',
            'missing' => 'The “SEO & brand” global set is missing. Run :command to create it.',
            'site_name' => 'Site name',
            'titles' => 'Titles',
            'page_title' => 'Page title',
            'default_description' => 'Default description',
            'no_description' => 'None: pages use their own text',
            'edit' => 'Edit brand and defaults',
        ],
        'search' => [
            'title' => 'Google Search',
            'not_imported' => 'Connected, not imported yet. It runs daily, or now from the Search Console screen.',
            'summary' => ':clicks clicks from :impressions appearances, :from to :to.',
            'updated' => 'Updated',
            'page' => 'Page',
            'clicks' => 'Clicks',
            'impressions' => 'Appearances',
            'position' => 'Position',
            'connect' => 'Connect Search Console',
            'connect_body' => 'See how often each page appears in Google, and how often it is clicked.',
            'manage' => 'Search Console',
        ],
        'files' => [
            'title' => 'What the site serves',
            'sitemap' => 'Sitemap',
            'robots' => 'robots.txt',
            'card' => 'Home share card',
        ],
    ],

    'search_console' => [
        'title' => 'Search Console',
        'status' => 'Connection',
        'connected' => 'Connected',
        'not_connected' => 'Not connected',
        'not_connected_body' => 'Whoever can change the SEO addon’s settings can connect Google Search Console here, to see how often each page is found and clicked.',
        'key' => 'Key',
        'property' => 'Property',
        'no_property' => 'None',
        'properties' => 'Property of each site',
        'last_import' => 'Last import',
        'never' => 'Never',
        'pages' => 'Pages with numbers',
        'import' => 'Import now',
        'disconnect' => 'Disconnect',
        'disconnect_confirm' => 'Remove the key? The numbers already imported stay until the next import.',
        'from_env' => '(from .env)',
        'overview' => 'Back to the overview',
        'setup' => [
            'heading' => 'Connect Google Search Console',
            'set_up' => 'Set up',
            'intro' => 'Shows how often each page appears in Google, and how often it is clicked. It takes about ten minutes, once.',
            'step_key' => 'Create a key in Google Cloud.',
            'step_key_body' => 'In a project (a new one is fine), :enable, then under :accounts create one (it needs no roles), open it → Keys → Add key → JSON. A file downloads.',
            'enable_api' => 'enable the Google Search Console API',
            'service_accounts' => 'Service accounts',
            'key_blocked' => 'Can’t add a key, or Google says the key is disabled? New Google Cloud projects often block service account keys. Someone who administers the organization can allow them in :policy (iam.disableServiceAccountKeyCreation); a key that exists but is disabled can be :enable.',
            'key_policy' => 'Organization policies',
            'key_enable' => 'enabled again',
            'key_added' => 'Key added',
            'remove' => 'Remove',
            'env_key_invalid' => 'SEO_SEARCH_CONSOLE_CREDENTIALS in .env is not a service account key, or the file it names can’t be read.',
            'upload' => 'Upload the key file',
            'paste' => 'Or paste its contents',
            'save_key' => 'Save the key',
            'stored' => 'It is stored on the server in storage/app/private, never in git; only its email is shown here.',
            'step_users' => 'Let the key read your property.',
            'step_users_body' => 'In :users, add :email as a Restricted user.',
            'users_link' => 'Search Console → Settings → Users and permissions',
            'the_email' => 'the key’s email',
            'copy_email' => 'Copy the email',
            'copied' => 'Copied',
            'step_property' => 'Name the property',
            'step_property_body' => 'as Search Console does: :domain for a domain, :prefix for an address prefix.',
            'save' => 'Save',
            'step_check' => 'Check it works',
            'step_check_body' => ', then bring in the numbers.',
            'check' => 'Check the connection',
            'schedule' => 'After that the numbers update daily at 04:30, when Laravel’s scheduler runs (:command every minute). Without it, come back and use Import now.',
            'failed' => 'That did not work.',
        ],
        'messages' => [
            'add_first' => 'Add the key and the property first.',
            'connected' => 'Connected: the key can read :property.',
            'imported' => 'Imported :count page.|Imported :count pages.',
            'key_in_env' => 'The key is set in .env (SEO_SEARCH_CONSOLE_CREDENTIALS).',
            'property_in_env' => 'The property is set in .env (SEO_SEARCH_CONSOLE_PROPERTY).',
            'not_a_key' => 'That is not a service account key: download one as JSON from Google Cloud → IAM & Admin → Service accounts → Keys.',
            'property_format' => 'Type it as Search Console names it: sc-domain:example.com, or https://example.com/ with the slash at the end.',
            'key_disabled' => 'Google says this key, or its service account, is disabled. Enable it in Google Cloud, then check again: :url',
            'api_disabled' => 'The Google Search Console API is not enabled in the key’s Google Cloud project. Enable it, wait a minute, and check again.',
            'key_refused' => 'Google refused the key: it may have been deleted in Google Cloud. Create a new key and upload it.',
            'not_a_user' => ':email is not a user of :property. Add it in Search Console → Settings → Users and permissions.',
            'the_service_account' => 'the service account',
            'no_property' => 'Search Console has no property :property. Check how it is named there: sc-domain:example.com for a domain, https://example.com/ for an address prefix.',
            'google_said' => 'Search Console said: :message',
            'error' => 'error :status',
        ],
    ],

    'redirects' => [
        'title' => 'Redirects',
        'import' => 'Import CSV',
        'export' => 'Export CSV',
        'create' => 'Create redirect',
        'intro' => 'Used only when an address would otherwise be a 404, so a page that exists always wins. “Automatic” ones were added when content moved.',
        'automatic' => 'Automatic',
        'yes' => 'Yes',
        'no' => 'No',
        'imported' => ':created added, :updated updated',
        'skipped' => ':summary; :count skipped. :errors',
        'import_failed' => 'The file could not be imported.',
    ],

    // The columns of the redirects and 404 listings.
    'listing' => [
        'from' => 'From',
        'to' => 'To',
        'site' => 'Site',
        'all_sites' => 'All sites',
        'status' => 'Status',
        'active' => 'Active',
        'hits' => 'Hits',
        'last_used' => 'Last used',
        'last_seen' => 'Last seen',
        'path' => 'Path',
        'first_seen' => 'First seen',
        'last_linked_from' => 'Last linked from',
    ],

    'redirect_form' => [
        'source' => 'From',
        'source_instructions' => 'A path on this site, such as `/old-page`. A `*` matches anything, `/blog/*` for example.',
        'target' => 'To',
        'target_instructions' => 'A path (`/new-page`) or a full address. `$1` is what the first `*` matched. Leave empty for 410.',
        'status' => 'Type',
        'status_301' => '301 Moved for good',
        'status_302' => '302 Moved for now',
        'status_410' => '410 Gone',
        'active' => 'Active',
        'site' => 'Site',
        'all_sites' => 'All sites',
        'site_instructions' => 'The site whose address this is. Empty: every site. A site’s own redirect wins over one for every site.',
        'used' => 'Used :count time.|Used :count times.',
        'used_last' => 'Used :count time, last :when.|Used :count times, last :when.',
        'automatic' => 'Added automatically when the content moved; saving here makes it a manual one.',
    ],

    'not_found' => [
        'title' => '404s',
        'intro' => 'Addresses visitors asked for that don’t exist, one row per address; the :max most recent are kept. Bots and scanner probes are left out.',
        'create' => 'Use a row’s menu to create a redirect for it.',
        'off' => 'The 404 log is turned off (:setting).',
    ],

    // The dashboard widget.
    'widget' => [
        'latest_report' => 'Link check',
        'pages' => ':count page to fix|:count pages to fix',
        'no_report' => 'Not checked yet.',
        'recent_404s' => 'Recent 404s',
        'none' => 'No missing pages recorded.',
    ],

    // The search and share previews on an entry's SEO tab.
    'preview' => [
        'failed' => 'The preview could not be worked out. Save the entry to see it.',
        'title' => 'Title',
        'description' => 'Description',
        'noindex' => 'Hidden from search engines (:robots): this result will not appear.',
        'drawing' => 'Drawing the card…',
        'no_image' => 'No share image',
        'from' => 'From :host',
        'count' => ':label: :count character, aim for :min–:max|:label: :count characters, aim for :min–:max',
        'under' => 'under :min',
        'over' => 'over :max',
    ],

    // Asked when saving would change a page's address.
    'confirm' => [
        'title' => 'This page’s address changes',
        'add' => 'Add redirect',
        'dont_add' => 'Don’t add',
        'moves' => 'Saving moves the page:',
        'question' => 'Add a 301 redirect from the old address, so links to it keep working?',
        'not_yet' => 'Don’t save yet',
        'not_saved' => 'Not saved.',
    ],

    'actions' => [
        'delete_confirm' => 'Delete this?|Delete these :count items?',
    ],

];
