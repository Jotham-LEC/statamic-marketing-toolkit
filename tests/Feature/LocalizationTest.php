<?php

use JothamLec\MarketingToolkit\Og\Generator;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Entry;

beforeEach(fn () => multilang());

test('a translation left to follow its origin takes the origin\'s title, description and settings', function () {
    seoGlobal([]);
    $about = entryIn('pages', 'about', ['title' => 'About us', 'description' => 'Who we are.', 'seo' => ['noindex' => true, 'og_title' => 'Meet us']]);
    $about->makeLocalization('fr')->save();
    $french = Entry::findByUri('/about', 'fr');
    $meta = metaFor($french, '/fr/about');

    expect($french->data()->all())->toBe([])
        ->and($meta->title)->toStartWith('About us')
        ->and($meta->description)->toBe('Who we are.')
        ->and($meta->robots)->toStartWith('noindex')
        ->and(app(Generator::class)->card($french)->title)->toBe('Meet us');

    $this->get('https://example.test/sitemap.xml')->assertOk()->assertDontSee('/fr/about');
});

test('a translation\'s own SEO group replaces the origin\'s, as the control panel shows it', function () {
    $about = entryIn('pages', 'about', ['title' => 'About us', 'seo' => ['noindex' => true, 'description' => 'Who we are.']]);
    $french = translationOf($about, 'fr', 'a-propos', ['seo' => ['title' => 'À propos']]);
    $meta = metaFor($french, '/fr/a-propos');

    // Unlinked from its origin in the control panel, the group is the translation's alone.
    expect($meta->title)->toBe('À propos')
        ->and($meta->robots)->not->toContain('noindex');
});

test('llms.txt lists a translation under its origin\'s title', function () {
    entryIn('pages', 'about', ['title' => 'About us', 'description' => 'Who we are.'])->makeLocalization('fr')->save();

    expect(Sites::as('fr', fn () => app(SiteSeo::class)->llmsTxt()))->toContain('- [About us](https://example.test/fr/about): Who we are.');
});

test('a path from the domain\'s root stays on the root, not under the language\'s folder', function () {
    config(['marketing-toolkit.og.enabled' => false]);
    AssetContainer::find('assets')->disk()->put('brand.png', file_get_contents(__DIR__.'/../fixtures/share.png'));
    seoGlobal(['default_image' => ['brand.png'], 'publisher_logo' => 'assets::brand.png']);
    $french = translationOf(entryIn('pages', 'about'), 'fr', 'a-propos');
    $meta = metaFor($french, '/fr/a-propos');

    expect(nodeOf($meta, 'Organization')['logo'])->toBe('https://example.test/assets/brand.png')
        // Statamic builds a Glide URL with the site's folder in it, and serves it there.
        ->and($meta->image['url'])->toStartWith('https://example.test/fr/img/asset/')
        ->and(metaFor($french, '/fr/a-propos', ['image' => '/images/card.jpg'])->image['url'])->toBe('https://example.test/images/card.jpg')
        // A path without the leading slash is under the site's own address, its home and WebSite node too.
        ->and(app(SiteSeo::class)->absolute('card.jpg'))->toBe('https://example.test/fr/card.jpg')
        ->and(nodeOf($meta, 'WebSite')['url'])->toBe('https://example.test/fr/');
});
