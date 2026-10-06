<?php

use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

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

test('each domain\'s sitemap lists that site\'s pages and terms only', function () {
    config(['seo.sitemap.taxonomies' => ['topics']]);
    Collection::make('services')->routes('services/{slug}')->sites(['cothinking'])->taxonomies(['topics'])->save();
    Taxonomy::make('topics')->sites(['default', 'cothinking'])->save();
    Term::make()->taxonomy('topics')->slug('gardens')->dataForLocale('default', ['title' => 'Gardens'])->dataForLocale('cothinking', ['title' => 'Gardens'])->save();

    entryIn('pages', 'about');
    entryOn('cothinking', 'pages', 'hello');
    entryOn('cothinking', 'services', 'websites', ['topics' => ['gardens']]);

    $cothinking = $this->get('https://cothink.test/sitemap.xml')->assertOk()->getContent();
    $default = $this->get('https://example.test/sitemap.xml')->assertOk()->getContent();

    expect($cothinking)->toContain('https://cothink.test/hello', 'https://cothink.test/services/websites', 'https://cothink.test/topics/gardens')
        ->not->toContain('example.test')
        ->and($default)->toContain('https://example.test/about')
        ->not->toContain('cothink.test')
        // No published entry on this site uses the term.
        ->not->toContain('/topics/gardens');
});
