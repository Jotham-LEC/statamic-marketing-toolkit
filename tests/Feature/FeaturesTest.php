<?php

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use JothamLec\MarketingToolkit\Commands\Install;
use JothamLec\MarketingToolkit\Listeners\RemakeFavicons;
use JothamLec\MarketingToolkit\ServiceProvider;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Package;
use Statamic\Facades\Addon;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

afterEach(function () {
    File::delete(resource_path('addons/marketing-toolkit.yaml'));
    Blueprint::find('globals.marketing')?->delete();
});

/**
 * Boots the addon's Features step again, as the next request would, after the features were saved.
 */
function rebootFeatures(): void
{
    (fn () => $this->bootFeatures())->call(app()->getProvider(ServiceProvider::class));
}

/**
 * Marketing settings, as `mt:install` makes it, on the default site and (with multisite()) the other.
 */
function marketingGlobal(): Statamic\Contracts\Globals\GlobalSet
{
    Blueprint::make('marketing')->setNamespace('globals')->setContents(['tabs' => Install::tabs('assets', 'marketing')])->save();
    $set = GlobalSet::findByHandle('marketing') ?? GlobalSet::make('marketing')->title('Marketing settings');

    if (Site::multiEnabled()) {
        $set->sites(Site::all()->mapWithKeys(fn ($each) => [$each->handle() => $each->handle() === 'default' ? null : 'default'])->all());
    }

    $set->save();

    return GlobalSet::findByHandle('marketing');
}

test('the Features tab of Settings saves what is off to the addon\'s settings', function () {
    $set = marketingGlobal();

    $set->in('default')->data(['gtm_id' => 'GTM-ABC1234', 'feature_sitemap' => false, 'feature_tracking' => false, 'feature_robots_txt' => true])->save();

    expect(Features::off())->toBe(['sitemap', 'tracking'])
        ->and(Addon::get(Package::NAME)->settings()->get('features_off'))->toBe(['sitemap', 'tracking']);

    $set->in('default')->set('feature_sitemap', true)->save();

    expect(Features::off())->toBe(['tracking']);
});

test('a save without the tab\'s values leaves the switches as they are', function () {
    Features::save(['tracking']);

    marketingGlobal()->in('default')->data(['gtm_id' => 'GTM-ABC1234'])->save();

    expect(Features::off())->toBe(['tracking']);
});

test('the tab starts as the addon\'s settings have the switches', function () {
    Features::save(['not_found', 'tracking']);
    Blueprint::find('globals.marketing')?->delete();
    GlobalSet::findByHandle('marketing')?->delete();

    $this->artisan('statamic:mt:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('marketing')->in('default')->data()->only(['feature_not_found', 'feature_tracking', 'feature_sitemap'])->all())
        ->toBe(['feature_not_found' => false, 'feature_tracking' => false]);
});

test('the tab is on the default site only, for whoever may change the addon\'s settings', function () {
    multisite();
    $set = marketingGlobal();

    $this->actingAs(cpUser(super: true));
    expect($set->in('default')->blueprint()->hasTab('features'))->toBeTrue()
        ->and($set->in('cothinking')->blueprint()->hasTab('features'))->toBeFalse();
    $this->get($set->in('default')->editUrl())->assertOk();

    $this->actingAs(cpUser(['view marketing toolkit']));
    expect(GlobalSet::findByHandle('marketing')->in('default')->blueprint()->hasTab('features'))->toBeFalse();
});

test('a module that is off is off in the config, and its pages answer 404 even with cached routes', function () {
    Features::save(['sitemap', 'llms_txt', 'favicons', 'tracking']);
    rebootFeatures();
    seoGlobal(['gtm_id' => 'GTM-ABC1234']);

    expect(config('marketing-toolkit.sitemap.enabled'))->toBeFalse()
        ->and(config('marketing-toolkit.favicons.enabled'))->toBeFalse()
        ->and(config('marketing-toolkit.robots_txt.enabled'))->toBeTrue();

    // Registered when the app booted, as with `php artisan route:cache`: the controller says no.
    $this->get('https://example.test/sitemap.xml')->assertNotFound();
    $this->get('https://example.test/llms.txt')->assertNotFound();
    $this->get('https://example.test/robots.txt')->assertOk();
    expect(renderAt('/', '<s:mt:head />'))->not->toContain('gtm.js');
});

test('the public routes are registered with their module off, so cached routes answer once it is back on', function () {
    config(['marketing-toolkit.sitemap.enabled' => false, 'marketing-toolkit.robots_txt.enabled' => false, 'marketing-toolkit.llms_txt.enabled' => false, 'marketing-toolkit.favicons.enabled' => false, 'marketing-toolkit.og.enabled' => false]);
    app('router')->setRoutes(new RouteCollection);
    require __DIR__.'/../../routes/web.php';
    app('router')->getRoutes()->refreshNameLookups();

    expect(collect(['mt.sitemap', 'mt.robots', 'mt.llms', 'mt.ads', 'mt.og.home', 'mt.indexnow.key', 'mt.favicons.favicon.ico'])->reject(fn ($name) => Route::has($name))->all())->toBe([]);
    $this->get('https://example.test/sitemap.xml')->assertNotFound();

    config(['marketing-toolkit.sitemap.enabled' => true]);
    $this->get('https://example.test/sitemap.xml')->assertOk();
});

test('listeners and middleware of modules that are off aren\'t registered', function () {
    Features::save(['favicons', 'sitemap', 'llms_txt', 'redirects', 'not_found', 'automatic_redirects', 'toolbar']);
    $provider = app()->getProvider(ServiceProvider::class);
    rebootFeatures();

    $listen = (fn () => $this->listen)->call($provider);
    $middleware = (fn () => $this->middlewareGroups)->call($provider);
    $discovered = (fn () => $this->autoloadFilesFromFolder('Listeners'))->call($provider);

    expect($listen)->toBe([])
        ->and($discovered)->not->toContain(RemakeFavicons::class)
        ->and($middleware)->toBe([])
        ->and((fn () => $this->subscribe)->call($provider))->toBe([]);
});

test('a module config/marketing-toolkit.php switches off is locked in the tab, and a save leaves it be', function () {
    config(['marketing-toolkit.indexnow.enabled' => false]);
    $set = marketingGlobal();
    $this->actingAs(cpUser(super: true));

    expect($set->in('default')->blueprint()->field('feature_indexnow')->config())->toMatchArray(['visibility' => 'read_only', 'default' => false])
        ->and($set->in('default')->blueprint()->field('feature_sitemap')->config())->not->toHaveKey('visibility');

    // Turning it on in the tab can't override the config.
    $set->in('default')->data(['feature_indexnow' => true, 'feature_sitemap' => false])->save();

    expect(Features::off())->toBe(['sitemap'])
        ->and(Features::offInConfig())->toBe(['indexnow']);
});

test('a module off in the tab isn\'t counted as off in the config', function () {
    Features::save(['sitemap']);
    rebootFeatures();

    expect(Features::offInConfig())->toBe([]);
});
