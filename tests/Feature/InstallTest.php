<?php

use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;

test('creates the SEO & brand global set and its blueprint, once', function () {
    $this->artisan('statamic:seo:install')->assertSuccessful();
    $this->artisan('statamic:seo:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')?->title())->toBe('SEO & brand')
        ->and(Blueprint::find('globals.seo')->fields()->all()->keys())->toContain('site_name', 'publisher_type', 'og_background', 'robots_extra', 'humans');
});
