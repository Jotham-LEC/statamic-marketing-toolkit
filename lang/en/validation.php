<?php

/*
 * The messages a redirect's checks give, in the form and for each CSV row.
 */
return [

    'redirect' => [
        'source_required' => 'Which address should be redirected?',
        'source_starts_with' => 'Start with / — the part of the address after the domain.',
        'source_query' => 'Leave out the query string (?…); addresses are matched without it.',
        'source_taken' => 'Another redirect already starts from this address.',
        'control_characters' => 'An address can’t contain line breaks or other control characters.',
        'target_required' => 'Where should it go? Only “410 Gone” needs no target.',
        'target_format' => 'Start with / for a page on this site, or https:// for another site.',
        'target_number' => 'The target uses a $ number the source has no * for.',
        'target_number_domain' => 'A $ number can only come after the domain and a /.',
        'points_back' => 'This sends the address back to itself.',
        'loop' => 'The redirect from that address leads back here, so the two would loop.',
        'loop_steps' => 'The redirects from that address lead back here after :steps steps, so visitors would go round in a loop.',
        'status' => 'Choose 301, 302 or 410.',
        'site' => 'Choose one of the sites, or none for every site.',
    ],

    // A CSV row that fails those checks.
    'csv_row' => 'Row :row: :message',

];
