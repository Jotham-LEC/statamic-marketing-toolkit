<?php

/*
 * The words of the link check: each check's name, what it says about a
 * page, and its screens. Reports keep the keys, not the words, so a report
 * reads in the language of whoever opens it. Where a line has a `|`, the
 * first form is for one and the second for more (see Laravel's pluralization).
 */
return [

    'rules' => [
        'broken_links' => 'Broken links',
        'external_links' => 'Broken links to other sites',
        'description' => 'Description',
        'og_image' => 'Share image',
        'render' => 'Page loads',
    ],

    'messages' => [
        // The end of a list of links that's too long to show whole.
        'and_more' => ':list and :count more',

        'links_broken' => 'Links to pages that don’t exist: :links.',
        'links_redirected' => 'Links that go through a redirect (link to the new address instead): :links.',
        'external_links_broken' => 'Links to other sites that lead nowhere: :links.',
        'description_missing' => 'No description: Google will pick text from the page for its search result.',
        'og_image_missing' => 'No share image: links to this page on LinkedIn, WhatsApp or Slack show no picture.',

        // A page that didn't load, and a check that stopped.
        'status' => 'The page answered with status :status.',
        'page_deleted' => 'The page was deleted while the check ran.',
        'stopped' => 'Stopped making progress.',
    ],

    'cp' => [
        'title' => 'Link check',
        'report' => 'Link check #:id',
        'back' => '← Link checks',
        'run' => 'Check now',
        'could_not_start' => 'The check could not start.',
        'intro' => 'Opens every published page, as a visitor would, and lists what to fix: links that lead nowhere, here or on other sites, and pages with no description or share image. It runs every week by itself.',
        'none' => 'No checks yet.',
        'run_first' => 'Run the first one.',
        'checking' => 'Checking pages…',

        // The list of checks, and one check's results.
        'column_report' => 'Check',
        'issues' => 'Pages to fix',
        'pages' => 'Pages',
        'finished' => 'Finished',
        'running' => 'Running',
        'failed' => 'Failed',
        'check' => 'What',
        'failing' => 'Pages',
        'warnings' => 'Warnings',
        'page' => 'Page',
        'hidden' => 'Hidden from Google',
        'fix' => 'Fix',

        'summary' => ':count page checked, finished :finished.|:count pages checked, finished :finished.',
        'with_issues' => ':count has something to fix.|:count have something to fix.',
        'all_good' => 'Nothing to fix.',
        'noindex' => ':count hidden from search engines (links checked only).',
        'not_rendered' => ':count didn’t load.',
        'flagged_by' => 'Pages flagged by “:check”.',
        'show_all' => 'Show every page to fix',
        'all_pages' => 'Pages with something to fix. Click a line above to see only those it flagged.',
    ],

];
