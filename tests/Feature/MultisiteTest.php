<?php

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use JothamLec\Seo\IndexNow\IndexNow;
use JothamLec\Seo\Redirects\Redirect;
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

test('IndexNow gets one request per domain, each with that domain\'s key file', function () {
    Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);
    $key = app(IndexNow::class)->key();

    entryIn('pages', 'about');
    entryOn('cothinking', 'pages', 'hello');
    entryOn('cothinking', 'pages', 'work');
    app()->terminate();

    Http::assertSentCount(2);
    Http::assertSent(fn (HttpRequest $request) => $request['host'] === 'example.test'
        && $request['keyLocation'] === "https://example.test/{$key}.txt"
        && $request['urlList'] === ['https://example.test/about']);
    Http::assertSent(fn (HttpRequest $request) => $request['host'] === 'cothink.test'
        && $request['keyLocation'] === "https://cothink.test/{$key}.txt"
        && $request['urlList'] === ['https://cothink.test/hello', 'https://cothink.test/work']);

    expect($this->get("https://cothink.test/{$key}.txt")->assertOk()->getContent())->toBe($key);
});

describe('redirects', function () {
    test('a rule applies on its own site; a rule for every site on each; a site\'s own wins', function () {
        Redirect::query()->create(['source' => '/old', 'target' => '/everywhere']);
        Redirect::query()->create(['site' => 'cothinking', 'source' => '/old', 'target' => '/here']);
        Redirect::query()->create(['site' => 'cothinking', 'source' => '/only-there', 'target' => '/there']);
        Redirect::query()->create(['site' => 'cothinking', 'source' => '/blog/*', 'target' => '/notes/$1']);
        Redirect::query()->create(['source' => '/blog/*', 'target' => '/essays/$1']);

        $this->get('https://cothink.test/old')->assertRedirect('https://cothink.test/here');
        $this->get('https://example.test/old')->assertRedirect('https://example.test/everywhere');
        $this->get('https://cothink.test/only-there')->assertRedirect('https://cothink.test/there');
        $this->get('https://example.test/only-there')->assertNotFound();
        $this->get('https://cothink.test/blog/x')->assertRedirect('https://cothink.test/notes/x');
        $this->get('https://example.test/blog/x')->assertRedirect('https://example.test/essays/x');
    });

    test('one rule per address per site, and loops are looked for on the sites a rule applies on', function () {
        Redirect::query()->create(['site' => 'cothinking', 'source' => '/b', 'target' => '/a']);

        expect(Redirect::validator(['site' => 'default', 'source' => '/b', 'target' => '/c', 'status' => 301])->passes())->toBeTrue()
            ->and(Redirect::validator(['site' => 'cothinking', 'source' => '/b', 'target' => '/c', 'status' => 301])->errors()->first('source'))->toContain('already starts')
            ->and(Redirect::validator(['site' => 'elsewhere', 'source' => '/z', 'target' => '/c', 'status' => 301])->errors()->has('site'))->toBeTrue()
            // On the other site nothing leads back.
            ->and(Redirect::validator(['site' => 'default', 'source' => '/a', 'target' => '/b', 'status' => 301])->passes())->toBeTrue()
            ->and(Redirect::validator(['site' => 'cothinking', 'source' => '/a', 'target' => '/b', 'status' => 301])->errors()->first('target'))->toContain('loop')
            // A rule for every site would loop on cothinking.
            ->and(Redirect::validator(['source' => '/a', 'target' => '/b', 'status' => 301])->errors()->first('target'))->toContain('loop');
    });

    test('ignoring letter case, an address is still one per site, matched on its own site', function () {
        config(['seo.redirects.case_sensitive' => false]);
        $this->actingAs(cpUser(['manage seo redirects']));
        Redirect::query()->create(['site' => 'cothinking', 'source' => '/About', 'target' => '/here']);
        Redirect::query()->create(['source' => '/about', 'target' => '/everywhere']);

        expect(Redirect::validator(['site' => 'cothinking', 'source' => '/ABOUT', 'target' => '/x', 'status' => 301])->errors()->first('source'))->toContain('already starts')
            ->and(Redirect::validator(['site' => 'default', 'source' => '/ABOUT', 'target' => '/x', 'status' => 301])->passes())->toBeTrue()
            ->and(Redirect::validator(['site' => 'cothinking', 'source' => '/b', 'target' => '/ABOUT', 'status' => 301])->passes())->toBeTrue();

        Redirect::query()->create(['site' => 'cothinking', 'source' => '/here', 'target' => '/b']);
        expect(Redirect::validator(['site' => 'cothinking', 'source' => '/B', 'target' => '/ABOUT', 'status' => 301])->errors()->first('target'))->toContain('loop');

        // The site's own rule, though another case, wins over the one for every site in the very case asked.
        $this->get('https://cothink.test/about')->assertRedirect('https://cothink.test/here');
        $this->get('https://example.test/ABOUT')->assertRedirect('https://example.test/everywhere');

        $csv = "source,target,status,active,site\n/ABOUT,/updated,301,1,cothinking\n";
        $result = $this->post(cp_route('seo.redirects.import'), ['file' => UploadedFile::fake()->createWithContent('r.csv', $csv)])->assertOk()->json();

        expect($result)->toMatchArray(['created' => 0, 'updated' => 1])
            ->and(Redirect::query()->where('site', 'cothinking')->orderBy('id')->value('target'))->toBe('/updated')
            ->and(Redirect::query()->whereNull('site')->value('target'))->toBe('/everywhere');
    });

    test('the form offers the sites, and a redirect made from a site\'s 404 starts on that site', function () {
        $this->actingAs(cpUser(['manage seo redirects']));

        $this->get(cp_route('seo.redirects.create', ['source' => '/missing', 'site' => 'cothinking']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('values.site', 'cothinking')
                ->where('blueprint.tabs.0.sections.0.fields.4.handle', 'site'));

        $this->postJson(cp_route('seo.redirects.store'), ['site' => 'cothinking', 'source' => '/missing', 'target' => '/found', 'status' => '301', 'active' => true])->assertOk();
        $this->postJson(cp_route('seo.redirects.store'), ['source' => '/missing', 'target' => '/found', 'status' => '301', 'active' => true])->assertOk();

        expect(Redirect::query()->orderBy('id')->pluck('site')->all())->toBe(['cothinking', null])
            ->and($this->getJson(cp_route('seo.redirects.listing', ['sort' => 'id']))->json('meta.columns.*.field'))->toContain('site');
    });

    test('CSV carries each rule\'s site', function () {
        $this->actingAs(cpUser(['manage seo redirects']));
        Redirect::query()->create(['source' => '/one', 'target' => '/1']);
        Redirect::query()->create(['site' => 'cothinking', 'source' => '/one', 'target' => '/uno']);

        expect($this->get(cp_route('seo.redirects.export'))->streamedContent())->toBe("source,target,status,active,site\n/one,/1,301,1,\n/one,/uno,301,1,cothinking\n");

        $csv = "source,target,status,active,site\n/one,/eins,301,1,cothinking\n/two,/2,301,1,cothinking\n/three,/3,301,1,nowhere\n";
        $result = $this->post(cp_route('seo.redirects.import'), ['file' => UploadedFile::fake()->createWithContent('r.csv', $csv)])->assertOk()->json();

        expect($result)->toMatchArray(['created' => 1, 'updated' => 1])
            ->and($result['errors'])->toHaveCount(1)
            ->and(Redirect::query()->orderBy('id')->get(['site', 'source', 'target'])->toArray())->toBe([
                ['site' => null, 'source' => '/one', 'target' => '/1'],
                ['site' => 'cothinking', 'source' => '/one', 'target' => '/eins'],
                ['site' => 'cothinking', 'source' => '/two', 'target' => '/2'],
            ]);
    });
});
