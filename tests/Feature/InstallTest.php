<?php

use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;

test('creates the SEO & brand global set and its blueprint, once', function () {
    $this->artisan('statamic:seo:install')->assertSuccessful();
    $this->artisan('statamic:seo:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')?->title())->toBe('SEO & brand')
        ->and(Blueprint::find('globals.seo')->fields()->all()->keys())->toContain('title_separator', 'publisher_type', 'og_background', 'robots_extra');
});

test('fills each empty brand field with what the site uses, so editors can see and change it', function () {
    entryIn('home', 'home', ['description' => 'We make things.']);

    $this->artisan('statamic:seo:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')->in('default')->data()->all())->toMatchArray([
        'title_separator' => '·',
        'default_description' => 'We make things.',
        'robots_disallow' => ['/cp/'],
    ]);
});

test('never overwrites a value an editor has set', function () {
    seoGlobal(['title_separator' => '|']);

    $this->artisan('statamic:seo:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')->in('default')->data()->all())
        ->toMatchArray(['title_separator' => '|', 'robots_disallow' => ['/cp/']])
        ->not->toHaveKey('default_description');
});

test('the separator gets a space on each side however it was typed', function (?string $typed, string $title) {
    seoGlobal(['title_separator' => $typed]);

    expect(metaFor(entryIn('pages', 'about'))->title)->toBe($title);
})->with([
    'trimmed' => ['|', 'About | Acme'],
    'spaced' => [' – ', 'About – Acme'],
    'empty' => [null, 'About · Acme'],
]);
