<?php

use Illuminate\Http\Request;
use JothamLec\MarketingToolkit\Reports\Runner;
use JothamLec\MarketingToolkit\SiteSeo;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Events\EntryScheduleReached;
use Statamic\Events\StacheCleared;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\StaticCaching\Cacher;

/**
 * @return list<string>
 */
function sitemapLocs(string $xml): array
{
    $sitemap = simplexml_load_string($xml);

    expect($sitemap)->not->toBeFalse();

    return array_map('strval', iterator_to_array($sitemap->xpath('//*[local-name()="loc"]'), false));
}

test('lists published pages, and leaves out drafts, hidden pages, redirects and pages canonical elsewhere, on this site or another', function () {
    entryIn('home', 'home');
    entryIn('pages', 'about');
    entryIn('pages', 'draft')->published(false)->save();
    entryIn('pages', 'hidden', ['seo' => ['noindex' => true]]);
    entryIn('pages', 'unlisted', ['seo' => ['sitemap' => false]]);
    entryIn('pages', 'moved', ['redirect' => 'https://elsewhere.example']);
    entryIn('pages', 'reprint', ['seo' => ['canonical' => 'https://times.example/x']]);
    entryIn('pages', 'self-canonical', ['seo' => ['canonical' => 'https://example.test/self-canonical']]);
    // Google: list only canonical addresses, and never one whose canonical names another page.
    entryIn('pages', 'duplicate', ['seo' => ['canonical' => 'https://example.test/about']]);

    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

    expect(sitemapLocs($response->getContent()))->toBe([
        'https://example.test',
        'https://example.test/about',
        'https://example.test/self-canonical',
    ])->and($response->headers->get('Set-Cookie'))->toBeNull();
});

test('each URL carries its last change', function () {
    entryIn('pages', 'about');

    expect($this->get('/sitemap.xml')->getContent())->toMatch('#<lastmod>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}</lastmod>#');
});

test('saving an entry refreshes the cached sitemap', function () {
    entryIn('pages', 'about');
    $this->get('/sitemap.xml');

    entryIn('pages', 'new-page');

    expect(sitemapLocs($this->get('/sitemap.xml')->getContent()))->toContain('https://example.test/new-page');
});

test('a collection given a new route, or sitemap settings changed by a deploy, refresh the cached sitemap', function () {
    entryIn('essays', 'first', [], '2026-01-02');
    $this->get('/sitemap.xml');

    // Saved without events, as Statamic rewrites a collection's entries when its route changes.
    Entry::make()->collection('pages')->slug('about')->data(['title' => 'About'])->saveQuietly();
    Collection::find('pages')->routes('info/{slug}')->save();

    expect($this->get('/sitemap.xml')->getContent())->toContain('about</loc>');

    config(['marketing-toolkit.sitemap.exclude_collections' => ['essays']]);

    expect(sitemapLocs($this->get('/sitemap.xml')->getContent()))->not->toContain('https://example.test/essays/first');
});

test('past the page size the sitemap becomes an index of numbered pages', function () {
    config(['marketing-toolkit.sitemap.per_page' => 2]);
    collect(['a', 'b', 'c'])->each(fn ($slug) => entryIn('pages', $slug));

    expect(sitemapLocs($this->get('/sitemap.xml')->getContent()))->toBe([
        'https://example.test/sitemap_1.xml',
        'https://example.test/sitemap_2.xml',
    ])
        ->and(sitemapLocs($this->get('/sitemap_2.xml')->getContent()))->toBe(['https://example.test/c']);

    $this->get('/sitemap_3.xml')->assertNotFound();
    $this->get('/sitemap_0.xml')->assertNotFound();
    $this->get('/sitemap_99999999999999999999.xml')->assertNotFound();
});

test('a project adds URLs that are not entries, from its subclass bound in a service provider', function () {
    app()->bind(SiteSeo::class, SitemapWithExtras::class);

    expect(sitemapLocs($this->get('/sitemap.xml')->getContent()))->toBe(['https://example.test/contact']);
});

test('a subclass named in config/marketing-toolkit.php still works', function () {
    config(['marketing-toolkit.class' => SitemapWithExtras::class]);

    expect(sitemapLocs($this->get('/sitemap.xml')->getContent()))->toBe(['https://example.test/contact']);
});

class SitemapWithExtras extends SiteSeo
{
    public function additionalSitemapUrls(): array
    {
        return [['loc' => 'https://example.test/contact', 'lastmod' => null]];
    }
}

test('robots.txt lets crawlers in on production, names the sitemap, and shuts them out elsewhere', function () {
    seoGlobal(['robots_extra' => "User-agent: GPTBot\nAllow: /"]);

    expect($this->get('/robots.txt')->assertOk()->getContent())->toBe(
        "User-agent: *\nDisallow: /cp/\n\nUser-agent: GPTBot\nAllow: /\n\nSitemap: https://example.test/sitemap.xml\n"
    );

    $this->app['env'] = 'staging';

    expect($this->get('/robots.txt')->getContent())->toBe("User-agent: *\nDisallow: /\n");
});

test('an unpublished entry is not in the sitemap even when found by URI', function () {
    entryIn('pages', 'later')->published(false)->save();

    expect(Entry::findByUri('/later'))->not->toBeNull()
        ->and(sitemapLocs($this->get('/sitemap.xml')->getContent()))->toBe([]);
});

test('an entry whose scheduled date arrives is listed without waiting for the next save', function () {
    Collection::make('news')->routes('news/{slug}')->dated(true)->futureDateBehavior('private')->save();
    entryIn('news', 'soon', date: now()->addDay()->format('Y-m-d'));

    expect($this->get('https://example.test/sitemap.xml')->getContent())->not->toContain('/news/soon');

    // Statamic's scheduler fires this once a minute for entries whose date has come.
    $this->travel(2)->days();
    EntryScheduleReached::dispatch(Entry::query()->where('slug', 'soon')->first());

    expect($this->get('https://example.test/sitemap.xml')->getContent())->toContain('https://example.test/news/soon');
});

test('more entries than one read takes are all listed, once each', function () {
    foreach (range(1, 501) as $i) {
        Entry::make()->collection('pages')->slug('page-'.$i)->data(['title' => 'Page '.$i])->saveQuietly();
    }

    $locs = sitemapLocs($this->get('https://example.test/sitemap.xml')->getContent());
    $report = app(Runner::class)->start();

    expect($locs)->toHaveCount(501)->toBe(array_values(array_unique($locs)))
        ->and($report->pages()->distinct()->count('url'))->toBe(501)
        ->and($report->pages_total)->toBe(501);
});

test('a project decides which terms have entries, for the sitemap and the reports alike', function () {
    // Statamic counts no entries for a taxonomy that isn't attached to their collection.
    Taxonomy::make('topics')->save();
    config(['marketing-toolkit.sitemap.taxonomies' => ['topics'], 'marketing-toolkit.class' => TermsWithEntries::class]);
    tap(Term::make()->taxonomy('topics')->slug('gardens')->data(['title' => 'Gardens']))->save();
    tap(Term::make()->taxonomy('topics')->slug('empty')->data(['title' => 'Empty']))->save();

    $report = app(Runner::class)->start();

    expect(sitemapLocs($this->get('https://example.test/sitemap.xml')->getContent()))->toBe(['https://example.test/topics/gardens'])
        ->and($report->pages()->pluck('url')->all())->toBe(['https://example.test/topics/gardens']);
});

class TermsWithEntries extends SiteSeo
{
    public function termHasEntries(TermContract $term): bool
    {
        return $term->slug() === 'gardens';
    }
}

test('clearing the Stache, as a deploy does, refreshes the cached sitemap', function () {
    entryIn('pages', 'about');
    $this->get('https://example.test/sitemap.xml');
    Entry::make()->collection('pages')->slug('quiet')->data(['title' => 'Quiet'])->saveQuietly();

    expect($this->get('https://example.test/sitemap.xml')->getContent())->not->toContain('/quiet');

    StacheCleared::dispatch();

    expect($this->get('https://example.test/sitemap.xml')->getContent())->toContain('https://example.test/quiet');
});

test('the sitemap and robots.txt are never kept by Statamic\'s static cache, which would miss the addon\'s refreshes', function () {
    config(['statamic.static_caching.strategy' => 'half']);
    entryIn('pages', 'about');

    foreach (['/about', '/sitemap.xml', '/robots.txt'] as $path) {
        $this->get('https://example.test'.$path)->assertOk();
    }

    $cached = fn (string $path) => app(Cacher::class)->hasCachedPage(Request::create('https://example.test'.$path));

    expect($cached('/about'))->toBeTrue()
        ->and($cached('/sitemap.xml'))->toBeFalse()
        ->and($cached('/robots.txt'))->toBeFalse();
});
