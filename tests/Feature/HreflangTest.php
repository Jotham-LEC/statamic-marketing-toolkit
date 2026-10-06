<?php

use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\YAML;

beforeEach(fn () => multilang());

/**
 * hreflang code => href, from a page's <head>.
 *
 * @return array<string, string>
 */
function hreflangs(string $html): array
{
    preg_match_all('#<link rel="alternate" hreflang="([^"]+)" href="([^"]+)">#', $html, $matches);

    return array_combine($matches[1], $matches[2]);
}

test('a page links to itself in each language, with the default site as x-default', function () {
    $about = entryIn('pages', 'about');
    translationOf($about, 'fr', 'a-propos');
    translationOf($about, 'de', 'uber-uns');

    $expected = [
        'en-US' => 'https://example.test/about',
        'fr' => 'https://example.test/fr/a-propos',
        'de' => 'https://de.example.test/uber-uns',
        'x-default' => 'https://example.test/about',
    ];

    expect(metaFor($about, '/about')->alternates)->toBe($expected)
        // The French page lists the same set, itself included.
        ->and(metaFor(Entry::findByUri('/a-propos', 'fr'), '/fr/a-propos')->alternates)->toBe($expected)
        ->and(hreflangs(renderAt('/about', '<s:seo:meta :entry="$entry" />', ['entry' => $about])))->toBe($expected);
});

test('the home page links to each language\'s home', function () {
    $home = entryIn('home', 'home');
    translationOf($home, 'fr', 'accueil');

    // As Statamic writes them, like the canonical.
    expect(metaFor($home)->alternates)->toBe([
        'en-US' => 'https://example.test',
        'fr' => 'https://example.test/fr',
        'x-default' => 'https://example.test',
    ]);
});

test('a term links to itself on each site it has entries on', function () {
    config(['seo.sitemap.taxonomies' => ['topics']]);
    Taxonomy::make('topics')->termTemplate('default')->sites(['default', 'fr', 'uk', 'de'])->save();
    Collection::findByHandle('pages')->taxonomies(['topics'])->save();
    Term::make()->taxonomy('topics')->slug('gardens')
        ->dataForLocale('default', ['title' => 'Gardens'])
        ->dataForLocale('fr', ['title' => 'Jardins'])
        ->save();

    $about = entryIn('pages', 'about', ['topics' => ['gardens']]);
    translationOf($about, 'fr', 'a-propos', ['topics' => ['gardens']]);

    $term = Term::find('topics::gardens')->in('fr');

    // Not on uk or de: no entry there uses the term.
    expect(metaFor($term, '/fr/topics/gardens')->alternates)->toBe([
        'en-US' => 'https://example.test/topics/gardens',
        'fr' => 'https://example.test/fr/topics/gardens',
        'x-default' => 'https://example.test/topics/gardens',
    ]);
});

test('a draft, noindexed or unlisted translation is left out', function () {
    $about = entryIn('pages', 'about');
    translationOf($about, 'fr', 'a-propos')->published(false)->save();
    translationOf($about, 'de', 'uber-uns', ['seo' => ['noindex' => true]]);
    translationOf($about, 'uk', 'about', ['seo' => ['sitemap' => false]]);

    expect(metaFor($about, '/about')->alternates)->toBe([]);
});

test('a page that is noindexed, or canonical elsewhere, gets no hreflang', function () {
    $about = entryIn('pages', 'about', ['seo' => ['canonical' => 'https://elsewhere.test/about']]);
    translationOf($about, 'fr', 'a-propos');
    $contact = entryIn('pages', 'contact', ['seo' => ['noindex' => true]]);
    translationOf($contact, 'fr', 'contact');

    expect(metaFor($about, '/about')->alternates)->toBe([])
        ->and(metaFor($contact, '/contact')->alternates)->toBe([])
        // Its translations don't point back at it either.
        ->and(metaFor(Entry::findByUri('/contact', 'fr'), '/fr/contact')->alternates)->toBe([]);
});

test('a page in one language, a second page of a listing and an error get none', function () {
    $about = entryIn('pages', 'about');
    $team = entryIn('pages', 'team');
    translationOf($team, 'fr', 'equipe');

    expect(metaFor($about, '/about')->alternates)->toBe([])
        ->and(metaFor($team, '/team?page=2')->alternates)->toBe([])
        ->and(metaFor($team, '/team', status: 404)->alternates)->toBe([]);
});

test('sites sharing a language get full locales; x-default can name another site or none', function () {
    $about = entryIn('pages', 'about');
    translationOf($about, 'uk', 'about-us');

    expect(metaFor($about, '/about')->alternates)->toBe([
        'en-US' => 'https://example.test/about',
        'en-GB' => 'https://example.test/uk/about-us',
        'x-default' => 'https://example.test/about',
    ]);

    config(['seo.hreflang.x_default' => 'uk']);
    expect(metaFor($about, '/about')->alternates['x-default'])->toBe('https://example.test/uk/about-us');

    config(['seo.hreflang.x_default' => false]);
    expect(metaFor($about, '/about')->alternates)->not->toHaveKey('x-default');

    config(['seo.hreflang.enabled' => false]);
    expect(metaFor($about, '/about')->alternates)->toBe([]);
});

test('without several sites there is no hreflang', function () {
    config(['statamic.system.multisite' => false]);

    expect(metaFor(entryIn('pages', 'about'), '/about')->alternates)->toBe([]);
});

test('the sitemap lists each domain\'s languages, with their alternates', function () {
    $about = entryIn('pages', 'about');
    translationOf($about, 'fr', 'a-propos');
    translationOf($about, 'de', 'uber-uns');
    entryIn('pages', 'only-english');

    $xml = $this->get('https://example.test/sitemap.xml')->assertOk()->getContent();
    $german = $this->get('https://de.example.test/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain('xmlns:xhtml="http://www.w3.org/1999/xhtml"')
        // French is under /fr/ on the same domain, so in its sitemap.
        ->and(sitemapLocsOf($xml))->toBe(['https://example.test/about', 'https://example.test/fr/a-propos', 'https://example.test/only-english'])
        ->and($xml)->toContain('<xhtml:link rel="alternate" hreflang="de" href="https://de.example.test/uber-uns"/>')
        ->toContain('<xhtml:link rel="alternate" hreflang="x-default" href="https://example.test/about"/>')
        ->and(substr_count($xml, 'hreflang="fr"'))->toBe(2)
        ->and(sitemapLocsOf($german))->toBe(['https://de.example.test/uber-uns'])
        ->and($german)->toContain('hreflang="en-US" href="https://example.test/about"');

    expect(simplexml_load_string($xml))->not->toBeFalse();
});

test('og:locale is the content\'s language, with the others as alternates', function () {
    $about = entryIn('pages', 'about');
    $french = translationOf($about, 'fr', 'a-propos');
    translationOf($about, 'de', 'uber-uns');

    $html = renderAt('/fr/a-propos', '<s:seo:meta :entry="$entry" />', ['entry' => $french]);

    expect($html)->toContain('<meta property="og:locale" content="fr_FR">')
        ->toContain('<meta property="og:locale:alternate" content="en_US">')
        ->toContain('<meta property="og:locale:alternate" content="de_DE">')
        ->not->toContain('og:locale:alternate" content="fr_FR"');
});

test('the WebPage node is in the content\'s language', function () {
    $french = translationOf(entryIn('pages', 'about'), 'fr', 'a-propos');

    // Even worked out from another site, as the control panel's preview does.
    expect(nodeOf(metaFor($french, '/about'), 'WebPage')['inLanguage'])->toBe('fr');
});

test('each language has its own SEO title, and the fieldset lets editors set it', function () {
    $about = entryIn('pages', 'about', ['seo' => ['title' => 'About Acme']]);
    $french = translationOf($about, 'fr', 'a-propos', ['seo' => ['title' => 'À propos d’Acme']]);

    expect(metaFor($about, '/about')->title)->toBe('About Acme')
        ->and(metaFor($french, '/fr/a-propos')->title)->toBe('À propos d’Acme');

    $fields = collect(YAML::file(__DIR__.'/../../resources/fieldsets/seo.yaml')->parse()['fields']);

    expect($fields->every(fn (array $field) => ($field['field']['localizable'] ?? false) === true))->toBeTrue();
});

function sitemapLocsOf(string $xml): array
{
    $sitemap = simplexml_load_string($xml);

    return array_map('strval', iterator_to_array($sitemap->xpath('//*[local-name()="url"]/*[local-name()="loc"]'), false));
}
