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

        'title_missing' => 'No <title>.',
        'title_short' => ':count character; aim for :min–:max. Short titles waste the space search results give them.|:count characters; aim for :min–:max. Short titles waste the space search results give them.',
        'title_long' => ':count characters; aim for :min–:max. Search results cut longer titles off.',
        'title_same' => 'Same title as :pages.',

        'description_missing' => 'No meta description; search engines will pick text from the page.',
        'description_short' => ':count character; aim for :min–:max.|:count characters; aim for :min–:max.',
        'description_long' => ':count characters; aim for :min–:max. Longer ones are cut off.',
        'description_same' => 'Same description as :pages.',

        'h1_missing' => 'No <h1>: the page has no main heading.',
        'h1_many' => ':count <h1> headings; keep one for the page’s main heading.',

        'canonical_missing' => 'No canonical link: search engines may split this page’s ranking across its addresses.',
        'canonical_relative' => 'The canonical link isn’t a full address: :url.',
        'canonical_elsewhere' => 'Points elsewhere: :url. Right for a piece first published there.',

        'noindex_in_sitemap' => 'The sitemap lists this page, but it tells search engines not to index it.',
        'images_without_alt' => ':count of :total images have no alt text.',
        'links_broken' => 'Links to pages that don’t exist: :links.',
        'links_redirected' => 'Links that go through a redirect (link to the new address instead): :links.',
        'external_links_broken' => 'Links to other sites that lead nowhere: :links.',
        'og_image_missing' => 'No og:image: links shared on social media show no picture.',
        'json_ld_invalid' => 'JSON-LD that doesn’t parse: :errors.',
        'json_ld_missing' => 'No JSON-LD structured data.',
        'orphan' => 'No other page links here; link to it from a related page.',

        // A page that didn't render, and a report that stopped.
        'status' => 'The page answered with status :status.',
        'page_deleted' => 'The page was deleted while the report ran.',
        'stopped' => 'Stopped making progress.',
    ],

    'cp' => [
        'title' => 'SEO reports',
        'report' => 'SEO report #:id',
        'back' => '← Reports',
        'settings' => 'Settings',
        'run' => 'Run report',
        'could_not_start' => 'The report could not start.',
        'intro' => 'A report renders every published page and checks it: titles, descriptions, headings, canonical links, the sitemap, image descriptions, links within the site, share images and structured data. Each page gets a score out of 100; the site’s score is their average.',
        'none' => 'No reports yet.',
        'run_first' => 'Run the first one.',
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

        // A report's score and what it covers.
        'out_of' => 'out of 100',
        'scored' => ':count page scored, finished :finished.|:count pages scored, finished :finished.',
        'noindex' => ':count hidden from search engines (listed, not scored).',
        'not_rendered' => ':count didn’t render',
        'scores_zero' => 'and score 0.',
        'weights' => 'Each check counts by how much it matters: 3 for what keeps a page out of search results, 2 for how it shows there, 1 for polish. A warning counts half.',
        'flagged_by' => 'Pages flagged by “:check”.',
        'show_all' => 'Show all pages',
        'all_pages' => 'All pages, lowest score first. Choose a check above to see only the pages it flagged.',
    ],

];
