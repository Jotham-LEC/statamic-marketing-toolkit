<?php

use JothamLec\MarketingToolkit\Og\Generator;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
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
