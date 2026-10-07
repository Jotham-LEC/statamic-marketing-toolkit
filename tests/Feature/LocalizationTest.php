<?php

use JothamLec\MarketingToolkit\Og\Generator;
use JothamLec\MarketingToolkit\SiteSeo;
use JothamLec\MarketingToolkit\Support\Sites;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
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

test('a share card of a site under a folder is on the domain\'s root, and shows that site\'s page', function () {
    seoGlobal([]);
    $about = entryIn('pages', 'about', ['title' => 'About us']);
    translationOf($about, 'fr', 'a-propos', ['title' => 'À propos']);
    entryIn('home', 'home');
    translationOf(Entry::findByUri('/', 'default'), 'fr', 'accueil');
    $seo = app(SiteSeo::class);

    // The card's path mirrors the page's: /fr/a-propos → /og/fr/a-propos.png.
    expect($seo->generatedImageUrl(Entry::findByUri('/a-propos', 'fr')))->toStartWith('https://example.test/og/fr/a-propos.png?v=')
        ->and($seo->generatedImageUrl(Entry::findByUri('/', 'fr')))->toStartWith('https://example.test/og/fr.png?v=')
        ->and($seo->generatedImageUrl($about))->toStartWith('https://example.test/og/about.png?v=')
        ->and($seo->generatedImageUrl(translationOf($about, 'de', 'uber-uns')))->toStartWith('https://de.example.test/og/uber-uns.png?v=');

    $this->get('https://example.test/og/fr/a-propos.png')->assertOk();
    $this->get('https://example.test/og/fr.png')->assertOk();
    $this->get('https://de.example.test/og/uber-uns.png')->assertOk();
    // The French page isn't on the default site, nor the English one under /fr/.
    $this->get('https://example.test/og/a-propos.png')->assertNotFound();
    $this->get('https://example.test/og/fr/about.png')->assertNotFound();
})->skip(! extension_loaded('imagick'), 'Share cards need PHP\'s imagick extension.');

test('the breadcrumbs of a page under a folder (/fr/) name its ancestors on its own site', function () {
    Collection::make('services')->routes('services/{slug}')->sites(['default', 'fr', 'uk', 'de'])->save();
    translationOf(entryIn('pages', 'services', ['title' => 'Services']), 'fr', 'services', ['title' => 'Prestations']);
    $web = entryOn('fr', 'services', 'web', ['title' => 'Sites web']);

    $crumbs = nodeOf(metaFor($web, '/fr/services/web'), 'BreadcrumbList')['itemListElement'];

    expect(collect($crumbs)->pluck('name')->all())->toBe(['Acme', 'Prestations', 'Sites web'])
        ->and(collect($crumbs)->pluck('item')->all())->toBe(['https://example.test/fr/', 'https://example.test/fr/services', 'https://example.test/fr/services/web']);
});

test('switching hreflang off or on is seen in the cached sitemap at once', function () {
    translationOf(entryIn('pages', 'about'), 'fr', 'a-propos');

    $this->get('https://example.test/sitemap.xml')->assertSee('hreflang="fr"', false);

    config(['marketing-toolkit.hreflang.enabled' => false]);
    $this->get('https://example.test/sitemap.xml')->assertDontSee('hreflang', false);

    config(['marketing-toolkit.hreflang.enabled' => true, 'marketing-toolkit.hreflang.x_default' => false]);
    $this->get('https://example.test/sitemap.xml')->assertSee('hreflang="fr"', false)->assertDontSee('x-default', false);
});
