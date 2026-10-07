<?php

/*
 * The words of SEO reports: each check's name, what it says about a page,
 * and the reports screens. Reports keep the keys, not the words, so a report
 * reads in the language of whoever opens it. Where a line has a `|`, the
 * first form is for one and the second for more (see Laravel's pluralization).
 */
return [

    'rules' => [
        'title_length' => 'Title length',
        'title_unique' => 'Unique title',
        'description_length' => 'Description length',
        'description_unique' => 'Unique description',
        'single_h1' => 'One main heading',
        'canonical' => 'Canonical address',
        'noindex_in_sitemap' => 'Hidden page in the sitemap',
        'image_alt' => 'Image descriptions',
        'broken_links' => 'Links within the site',
        'og_image' => 'Share image',
        'json_ld' => 'Structured data',
        'orphan_pages' => 'Linked from another page',
        'external_links' => 'Links to other sites',
        'render' => 'Page renders',
    ],

    'messages' => [
        // The end of a list of pages or links that's too long to show whole.
        'and_more' => ':list and :count more',
        'characters' => ':count character.|:count characters.',

        'title_missing' => 'The page has no <title>.',
        'title_short' => 'The title is :count character long, and the aim is :min to :max. A short title wastes the space that search results give it.|The title is :count characters long, and the aim is :min to :max. A short title wastes the space that search results give it.',
        'title_long' => 'The title is :count characters long, and the aim is :min to :max. Search results cut off longer titles.',
        'title_same' => 'This page has the same title as :pages.',

        'description_missing' => 'The page has no meta description, so search engines will choose text from the page instead.',
        'description_short' => 'The description is :count character long, and the aim is :min to :max.|The description is :count characters long, and the aim is :min to :max.',
        'description_long' => 'The description is :count characters long, and the aim is :min to :max. Search results cut off longer descriptions.',
        'description_same' => 'This page has the same description as :pages.',

        'h1_missing' => 'The page has no <h1>, so it has no main heading.',
        'h1_many' => 'The page has :count <h1> headings, but it should keep only one for its main heading.',

        'canonical_missing' => 'The page has no canonical link, so search engines may split its ranking across its different addresses.',
        'canonical_relative' => 'The canonical link isn’t a full address (:url).',
        'canonical_elsewhere' => 'The canonical link points to :url, which is only right for a piece that was first published there.',

        'noindex_in_sitemap' => 'The sitemap lists this page, but the page tells search engines not to index it.',
        'images_without_alt' => ':count of the :total images have no alt text.',
        'links_broken' => 'Some links point to pages that don’t exist (:links).',
        'links_redirected' => 'Some links go through a redirect (:links), so link to the new address instead.',
        'external_links_broken' => 'Some links to other sites lead nowhere (:links).',
        'og_image_missing' => 'The page has no og:image, so links to it shared on social media show no picture.',
        'json_ld_invalid' => 'The page has JSON-LD that can’t be read (:errors).',
        'json_ld_missing' => 'The page has no JSON-LD structured data.',
        'orphan' => 'No other page links here, so add a link to it from a related page.',

        // A page that didn't render, and a report that stopped.
        'status' => 'The page answered with status :status.',
        'render_failed' => 'The page couldn’t be rendered (:exception). The full error is in the site’s log.',
        'page_deleted' => 'The page was deleted while the report was running.',
        'stopped' => 'The report stopped making progress.',
    ],

    'cp' => [
        'title' => 'SEO reports',
        'report' => 'SEO report #:id',
        'back' => '← Reports',
        'settings' => 'Settings',
        'tab_reports' => 'Reports',
        'tab_settings' => 'Settings',
        'export' => 'Export CSV',
        'settings_saved' => 'Your report settings have been saved.',
        'run' => 'Run report',
        'could_not_start' => 'The report could not be started.',
        'intro' => 'A report opens every published page and checks its title, description, headings, canonical link, sitemap entry, image descriptions, internal links, share image, and structured data. Each page gets a score out of 100, and the site’s score is the average of those scores.',
        'none' => 'There are no reports yet.',
        'run_first' => 'Run the first one to see how the site scores.',
        'checking' => 'Checking pages…',

        // The list of reports, and a report's checks and pages.
        'column_report' => 'Report',
        'score' => 'Score',
        'pages' => 'Pages',
        'finished' => 'Finished',
        'running' => 'Running',
        'failed' => 'Failed',
        'check' => 'Check',
        'weight' => 'Weight',
        'failing' => 'Failing',
        'warnings' => 'Warnings',
        'page' => 'Page',
        'in_sitemap' => 'In sitemap',
        'yes' => 'Yes',
        'no' => 'No',
        'hidden' => 'Hidden',
        'fix' => 'Fix',

        // The columns of a report's CSV export.
        'csv' => [
            'url' => 'Address',
            'title' => 'Title',
            'score' => 'Score',
            'failed' => 'Failed checks',
            'warnings' => 'Warnings',
        ],

        // A report's score and what it covers.
        'out_of' => 'out of 100',
        'scored' => 'The report scored :count page and finished :finished.|The report scored :count pages and finished :finished.',
        'noindex' => ':count page is hidden from search engines, so it is listed but not scored.|:count pages are hidden from search engines, so they are listed but not scored.',
        'not_rendered' => ':count page didn’t render|:count pages didn’t render',
        'scores_zero' => 'and so scored 0.',
        'weights' => 'Each check counts according to how much it matters. A check that keeps a page out of search results counts 3, a check on how the page appears there counts 2, and a finishing touch counts 1. A warning counts half as much as a failure.',
        'flagged_by' => 'These are the pages flagged by “:check”.',
        'show_all' => 'Show all pages',
        'all_pages' => 'All pages are listed with the lowest score first. Choose a check above to see only the pages it flagged.',
    ],

];
