<?php

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use JothamLec\MarketingToolkit\Listeners\RemakeFavicons;
use JothamLec\MarketingToolkit\ServiceProvider;
use JothamLec\MarketingToolkit\Support\Features;
use JothamLec\MarketingToolkit\Support\Package;
use Statamic\Facades\Addon;

afterEach(fn () => File::delete(resource_path('addons/marketing-toolkit.yaml')));

/**
 * Boots the addon's Features step again, as the next request would, after the features were saved.
 */
function rebootFeatures(): void
{
    (fn () => $this->bootFeatures())->call(app()->getProvider(ServiceProvider::class));
}

test('the Features screen saves what is off, for whoever may change the addon\'s settings', function () {
    $this->actingAs(cpUser(super: true));

    $this->get(cp_route('mt.features.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('marketing-toolkit::Features', false)
        ->where('values.sitemap', true)
        ->where('blueprint.tabs.0.sections.2.fields.0.handle', 'tracking'));

    $this->postJson(cp_route('mt.features.update'), [...array_fill_keys(array_keys(Features::MODULES), true), 'sitemap' => false, 'tracking' => false])->assertOk();

    expect(Features::off())->toBe(['sitemap', 'tracking'])
        ->and(Addon::get(Package::NAME)->settings()->get('features_off'))->toBe(['sitemap', 'tracking']);

    $this->get(cp_route('mt.features.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('values.sitemap', false)->where('values.robots_txt', true));
});

test('a module the request leaves out keeps its state', function () {
    Features::save(['tracking']);
    $this->actingAs(cpUser(super: true));

    $this->postJson(cp_route('mt.features.update'), ['not_found' => false])->assertOk();

    expect(Features::off())->toBe(['not_found', 'tracking']);
});

test('someone who may not change the addon\'s settings can\'t open it, or see it in the nav', function () {
    $this->actingAs(cpUser(['view marketing toolkit']));

    $this->get(cp_route('mt.features.index'))->assertForbidden();
    $this->postJson(cp_route('mt.features.update'), ['sitemap' => false])->assertForbidden();
    expect(collect(toolsNav()->get('SEO')->resolveChildren()->children())->map->display()->all())->not->toContain('Features');
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
    Features::save(['favicons', 'sitemap', 'llms_txt', 'redirects', 'not_found', 'automatic_redirects']);
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

test('a module config/marketing-toolkit.php switches off shows off, locked, and a save leaves it be', function () {
    config(['marketing-toolkit.indexnow.enabled' => false]);
    $this->actingAs(cpUser(super: true));

    $this->get(cp_route('mt.features.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('values.indexnow', false)
        ->where('values.sitemap', true)
        ->where('blueprint.tabs.0.sections.0.fields.4.handle', 'indexnow')
        ->where('blueprint.tabs.0.sections.0.fields.4.visibility', 'read_only')
        ->where('blueprint.tabs.0.sections.0.fields.4.instructions', 'Tells Bing and others when a page changes. Off in config/marketing-toolkit.php.'));

    // Turning it on here can't override the config; turning it off here would only be saved twice.
    $this->postJson(cp_route('mt.features.update'), ['indexnow' => true, 'sitemap' => false])->assertOk();

    expect(Features::off())->toBe(['sitemap'])
        ->and(Features::offInConfig())->toBe(['indexnow']);
});

test('a module off on the screen isn\'t counted as off in the config', function () {
    Features::save(['sitemap']);
    rebootFeatures();

    expect(Features::offInConfig())->toBe([]);
});
