<?php

use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;

/*
 * Statamic Pro with two sites on two domains: `default` (example.test) and
 * `cothinking` (cothink.test). Single-site behaviour is what every other test covers.
 */
beforeEach(fn () => multisite());

test('install puts the brand global on every site, the others inheriting from the default', function () {
    entryIn('home', 'home', ['description' => 'We make things.']);

    $this->artisan('statamic:seo:install')->assertSuccessful();
    $set = GlobalSet::findByHandle('seo');

    expect($set->origins()->all())->toBe(['default' => null, 'cothinking' => 'default'])
        ->and($set->in('default')->data()->all())->toMatchArray(['title_separator' => '·', 'default_description' => 'We make things.'])
        // Left empty, so it follows the default site's values rather than a copy of them.
        ->and($set->in('cothinking')->data()->all())->toBe([])
        ->and($set->in('cothinking')->value('title_separator'))->toBe('·');
});

test('install leaves an existing set\'s sites alone and says which it is missing', function () {
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => []])->save();
    GlobalSet::make('seo')->title('SEO & brand')->sites(['default' => null])->save();

    $this->artisan('statamic:seo:install')
        ->expectsOutputToContain('cothinking')
        ->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')->sites()->all())->toBe(['default']);
});

test('a site reads its own brand values, and what it leaves empty from its origin', function () {
    seoGlobal(['title_separator' => '|', 'twitter_handle' => 'acme', 'allow_ai_training' => false]);
    seoGlobal(['twitter_handle' => 'cothinking'], 'cothinking');

    $meta = metaFor(entryOn('cothinking', 'pages', 'about'), 'https://cothink.test/about');

    expect($meta->title)->toBe('About | CoThinking')
        ->and($meta->twitterSite)->toBe('@cothinking');

    $this->get('https://cothink.test/robots.txt')->assertSee('User-agent: GPTBot');
    expect(metaFor(entryIn('pages', 'about'))->twitterSite)->toBe('@acme');
});
