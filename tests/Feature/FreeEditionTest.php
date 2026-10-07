<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use JothamLec\MarketingToolkit\Actions\CreateRedirect;
use JothamLec\MarketingToolkit\Actions\DeleteSeoRecords;
use JothamLec\MarketingToolkit\Conversions\Attribution;
use JothamLec\MarketingToolkit\Favicons\Favicons;
use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use JothamLec\MarketingToolkit\SearchConsole\Client;
use JothamLec\MarketingToolkit\ServiceProvider;
use JothamLec\MarketingToolkit\Support\Edition;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Tests\FreeEdition;
use JothamLec\MarketingToolkit\Tracking\Tracking;
use JothamLec\MarketingToolkit\Widgets\SeoWidget;
use Statamic\Actions\Action;
use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\Form;
use Statamic\Facades\Permission;
use Statamic\Widgets\Widget;

uses(FreeEdition::class);

test('the addon runs as the free edition unless a site sets Pro', function () {
    expect(Edition::pro())->toBeFalse()
        ->and(Addon::get(Edition::PACKAGE)->editions()->all())->toBe(['free', 'pro'])
        // Statamic CMS Pro is its own setting, and multi-site still needs it.
        ->and(config('statamic.editions.pro'))->toBeFalse();
});

test('no generated share card: og:image falls back to the brand\'s default image', function () {
    AssetContainer::find('assets')->disk()->put('share.png', file_get_contents(__DIR__.'/../fixtures/share.png'));
    seoGlobal(['default_image' => 'share.png']);
    $about = entryIn('pages', 'about');

    expect(config('seo.og.enabled'))->toBeFalse()
        ->and(metaFor($about, '/about')->image['url'])->toStartWith('https://example.test/img/')->toContain('/share.png');

    $this->get('https://example.test/og/about.png')->assertNotFound();
    $this->get('https://example.test/og.png')->assertNotFound();
});

test('Pro\'s control panel screens show what Pro adds', function (string $route) {
    $this->actingAs(cpUser(super: true));

    $this->get(cp_route($route))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('seo::ProOnly', false)
        ->where('upgradeUrl', Edition::marketplaceUrl()));
})->with(['seo.reports.index', 'seo.404s.index', 'seo.search-console.index', 'seo.features.index']);

test('Pro\'s control panel requests are not found', function (string $method, string $route, array $parameters = []) {
    $this->actingAs(cpUser(super: true));

    $this->json($method, cp_route($route, $parameters))->assertNotFound();
})->with([
    ['GET', 'seo.reports.index'],
    ['POST', 'seo.reports.run'],
    ['GET', 'seo.404s.index'],
    ['GET', 'seo.search-console.index'],
    ['POST', 'seo.search-console.check'],
    ['GET', 'seo.redirects.export'],
    ['POST', 'seo.redirects.import'],
    ['POST', 'seo.redirects.check'],
    ['POST', 'seo.redirects.choice'],
    ['POST', 'seo.preview.card'],
]);

test('the nav, permissions and overview show only what the free edition has', function () {
    seoGlobal([]);
    $this->actingAs(cpUser(super: true));

    $seo = toolsNav()->get('SEO');
    $permissions = Permission::boot()->all()->filter(fn ($permission) => $permission->group() === 'seo')->map->value()->values()->all();

    expect(collect($seo->resolveChildren()->children())->map->display()->all())->toBe(['Redirects', 'Brand & defaults'])
        ->and($permissions)->toBe(['view seo', 'manage seo redirects']);

    $this->get(cp_route('seo.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('upgradeUrl', Edition::marketplaceUrl())
        ->where('report', null)
        ->where('notFound', null)
        ->where('search', null)
        ->where('searchConsole', null)
        ->where('redirects.active', 0)
        // No home share card among the files the site serves.
        ->where('files', fn ($files) => collect($files)->pluck('label')->all() === ['Sitemap', 'robots.txt', 'llms.txt']));

    $this->get(cp_route('seo.redirects.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('exportUrl', null)
        ->where('importUrl', null));
});

test('no schedule, report or Search Console commands, widget, or redirect-from-404 action', function () {
    $schedule = new Schedule;
    (fn () => $this->schedule($schedule))->call(app()->getProvider(ServiceProvider::class));
    config(['seo.search_console' => ['credentials' => '{"x":1}', 'property' => 'sc-domain:example.test']]);

    expect($schedule->events())->toBe([])
        ->and(Artisan::all())->toHaveKey('statamic:seo:install')
        ->not->toHaveKey('statamic:seo:report')
        ->not->toHaveKey('statamic:seo:search-console')
        ->and(app('statamic.extensions')[Widget::class]->keys()->all())->not->toContain(SeoWidget::handle())
        ->and(app('statamic.extensions')[Action::class]->keys()->all())->toContain(DeleteSeoRecords::handle())
        ->not->toContain(CreateRedirect::handle())
        ->and(app(Client::class)->configured())->toBeFalse()
        ->and(app(Client::class)->configuredForAnySite())->toBeFalse()
        ->and(Addon::get(Edition::PACKAGE)->hasSettingsBlueprint())->toBeFalse();
});

test('manual redirects and 410s work; a 404 isn\'t logged', function () {
    Redirect::query()->create(['source' => '/old', 'target' => '/new', 'status' => 301, 'active' => true]);
    Redirect::query()->create(['source' => '/gone', 'target' => null, 'status' => 410, 'active' => true]);

    $this->get('/old')->assertStatus(301)->assertRedirect('https://example.test/new');
    $this->get('/gone')->assertStatus(410);
    $this->get('/missing', ['User-Agent' => 'Mozilla/5.0'])->assertNotFound();

    expect(MissingPath::query()->count())->toBe(0);
});

test('a page that moves gets no automatic redirect', function () {
    $about = entryIn('pages', 'about');
    $about->slug('about-us')->save();

    expect(Redirect::query()->count())->toBe(0);
});

test('the sitemap, robots.txt and IndexNow work as in Pro', function () {
    Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);
    seoGlobal([]);
    entryIn('pages', 'about');
    app()->terminate();

    $this->get('https://example.test/sitemap.xml')->assertOk()->assertSee('https://example.test/about', false);
    $this->get('https://example.test/robots.txt')->assertOk()->assertSee('Sitemap: https://example.test/sitemap.xml', false);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.indexnow.org/indexnow');
});

test('several languages: no hreflang, and the sitemap lists the default site alone', function () {
    multilang();
    $about = entryIn('pages', 'about');
    translationOf($about, 'fr', 'a-propos');
    translationOf($about, 'de', 'uber-uns');

    expect(metaFor($about, '/about')->alternates)->toBe([])
        ->and(metaFor($about, '/about')->localeAlternates)->toBe([])
        ->and(renderAt('/about', '<s:seo:meta :entry="$entry" />', ['entry' => $about]))->not->toContain('hreflang');

    $this->get('https://example.test/sitemap.xml')->assertOk()
        ->assertSee('https://example.test/about', false)
        ->assertDontSee('https://example.test/fr/a-propos', false)
        ->assertDontSee('xhtml:link', false);
});

test('several sites: another site\'s domain has no sitemap, robots.txt or icons, and IndexNow leaves it out', function () {
    multisite();
    Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);
    app(Favicons::class)->flush();
    AssetContainer::find('assets')->disk()->put('icon.png', file_get_contents(__DIR__.'/../fixtures/share.png'));
    seoGlobal(['favicon' => 'icon.png']);
    entryIn('pages', 'about');
    Entry::make()->collection('pages')->locale('cothinking')->slug('team')->data(['title' => 'Team'])->save();
    app()->terminate();

    $this->get('https://example.test/sitemap.xml')->assertOk()->assertSee('https://example.test/about', false);
    $this->get('https://cothink.test/sitemap.xml')->assertNotFound();
    $this->get('https://cothink.test/robots.txt')->assertNotFound();
    $this->get('https://cothink.test/favicon.ico')->assertNotFound();
    expect(renderAt('https://cothink.test/', '<s:seo:head />'))->not->toContain('site.webmanifest')
        ->and(renderAt('https://example.test/', '<s:seo:head />'))->toContain('site.webmanifest');

    Http::assertSent(fn ($request) => $request['host'] === 'example.test');
    Http::assertNotSent(fn ($request) => $request['host'] === 'cothink.test');
});

test('several sites: the overview is the default site\'s, with a Pro card for the others', function () {
    multisite();
    seoGlobal([]);
    $this->actingAs(cpUser(super: true));
    session(['statamic.cp.selected-site' => 'cothinking']);

    $this->get(cp_route('seo.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('severalSites', true)
        ->where('siteName', 'Acme'));
});

test('tracking tags work; Consent Mode is Pro, so none of its defaults print', function () {
    seoGlobal(['gtm_id' => 'GTM-ABC1234', 'meta_pixel_id' => '123456789012', 'consent_mode' => true, 'consent_regions' => ['EEA']]);
    $head = renderAt('/', '<s:seo:head />');

    expect(app(Tracking::class)->consent())->toBeNull()
        ->and($head)->toContain('gtm.js')->toContain("fbq('init'")
        ->not->toContain("gtag('consent'")
        ->not->toContain('mtConsent');
});

test('favicons are made from the brand\'s icon', function () {
    app(Favicons::class)->flush();
    AssetContainer::find('assets')->disk()->put('icon.png', file_get_contents(__DIR__.'/../fixtures/share.png'));
    seoGlobal(['favicon' => 'icon.png']);

    $this->get('https://example.test/favicon.ico')->assertOk();
    expect(renderAt('/', '<s:seo:head />'))->toContain('rel="apple-touch-icon"');
});

test('seo:install --forms adds no lead source fields: they are Pro', function () {
    // Forms and their blueprints are files that outlive a test: start without them, and leave none.
    $clean = fn () => [Blueprint::find('forms.free_quote')?->delete(), Form::find('free_quote')?->delete()];
    $clean();
    Form::make('free_quote')->title('Quote')->save();

    try {
        $this->artisan('statamic:seo:install', ['--forms' => true])->expectsOutputToContain('Leads are a Pro feature')->assertSuccessful();
        expect(Blueprint::find('forms.free_quote')?->fields()->all()->keys() ?? collect())->not->toContain('utm_source');
    } finally {
        $clean();
    }
});

test('no lead source and no conversions: they are Pro', function () {
    seoGlobal(['gtm_id' => 'GTM-ABC1234']);
    $form = Form::make('contact')->title('Contact');
    $form->save();
    Blueprint::make('contact')->setNamespace('forms')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [['handle' => 'email', 'field' => ['type' => 'text']]]]]]]])->save();
    Attribution::addToForms();

    $this->withUnencryptedCookie('mt_source', json_encode(['source' => 'linkedin']))
        ->post('https://example.test/!/forms/contact', ['email' => 'a@example.test'])
        ->assertCookieMissing('mt_conversion');

    expect(renderAt('/', '<s:seo:head />'))->not->toContain('mtConversion')->not->toContain('mt_source=')
        ->and(Form::find('contact')->submissions()->first()?->get('utm_source'))->toBeNull();
});

test('no campaign link fields: the UTM builder is Pro', function () {
    $this->actingAs(cpUser(['manage seo redirects']));

    $this->postJson(cp_route('seo.redirects.store'), ['source' => '/go/x', 'target' => '/offer', 'status' => '302', 'active' => true, 'utm_source' => 'linkedin'])->assertOk();

    expect(Redirect::query()->sole()->target)->toBe('/offer');
    $this->get(cp_route('seo.redirects.create'))->assertInertia(fn (AssertableInertia $page) => $page->missing('blueprint.tabs.0.sections.1'));
});

test('llms.txt and ads.txt work as in Pro', function () {
    seoGlobal(['ads_txt' => 'google.com, pub-1, DIRECT']);
    entryIn('pages', 'about');

    $this->get('https://example.test/llms.txt')->assertOk()->assertSee('https://example.test/about', false);
    $this->get('https://example.test/ads.txt')->assertOk()->assertSee('pub-1');
});

test('the Features screen is Pro: saved switches are ignored', function () {
    Features::save(['sitemap']);

    try {
        (fn () => $this->bootEdition())->call(app()->getProvider(ServiceProvider::class));
        $this->actingAs(cpUser(super: true));

        expect(config('seo.sitemap.enabled'))->toBeTrue();
        $this->get(cp_route('seo.features.index'))->assertInertia(fn (AssertableInertia $page) => $page->component('seo::ProOnly', false));
    } finally {
        File::delete(resource_path('addons/marketing-toolkit.yaml'));
    }
});
