<?php

use Inertia\Testing\AssertableInertia;
use JothamLec\MarketingToolkit\Listeners\RemakeFavicons;
use JothamLec\MarketingToolkit\ServiceProvider;
use JothamLec\MarketingToolkit\Support\Edition;
use JothamLec\MarketingToolkit\Support\Features;
use Statamic\Facades\Addon;

afterEach(fn () => File::delete(resource_path('addons/marketing-toolkit.yaml')));

/**
 * Boots the addon's edition step again, as the next request would, after the features were saved.
 */
function rebootFeatures(): void
{
    (fn () => $this->bootEdition())->call(app()->getProvider(ServiceProvider::class));
}

test('the Features screen saves what is off, for whoever may change the addon\'s settings', function () {
    $this->actingAs(cpUser(super: true));

    $this->get(cp_route('seo.features.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('seo::Features', false)
        ->where('values.sitemap', true)
        ->where('blueprint.tabs.0.sections.2.fields.0.handle', 'tracking'));

    $this->postJson(cp_route('seo.features.update'), [...array_fill_keys(array_keys(Features::MODULES), true), 'sitemap' => false, 'tracking' => false])->assertOk();

    expect(Features::off())->toBe(['sitemap', 'tracking'])
        ->and(Addon::get(Edition::PACKAGE)->settings()->get('features_off'))->toBe(['sitemap', 'tracking']);

    $this->get(cp_route('seo.features.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('values.sitemap', false)->where('values.robots_txt', true));
});

test('a module the request leaves out keeps its state', function () {
    Features::save(['tracking']);
    $this->actingAs(cpUser(super: true));

    $this->postJson(cp_route('seo.features.update'), ['not_found' => false])->assertOk();

    expect(Features::off())->toBe(['not_found', 'tracking']);
});

test('someone who may not change the addon\'s settings can\'t open it, or see it in the nav', function () {
    $this->actingAs(cpUser(['view seo']));

    $this->get(cp_route('seo.features.index'))->assertForbidden();
    $this->postJson(cp_route('seo.features.update'), ['sitemap' => false])->assertForbidden();
    expect(collect(toolsNav()->get('SEO')->resolveChildren()->children())->map->display()->all())->not->toContain('Features');
});

test('a module that is off is off in the config, and its pages answer 404 even with cached routes', function () {
    Features::save(['sitemap', 'llms_txt', 'favicons', 'tracking']);
    rebootFeatures();
    seoGlobal(['gtm_id' => 'GTM-ABC1234']);

    expect(config('seo.sitemap.enabled'))->toBeFalse()
        ->and(config('seo.favicons.enabled'))->toBeFalse()
        ->and(config('seo.robots_txt'))->toBeTrue();

    // Registered when the app booted, as with `php artisan route:cache`: the controller says no.
    $this->get('https://example.test/sitemap.xml')->assertNotFound();
    $this->get('https://example.test/llms.txt')->assertNotFound();
    $this->get('https://example.test/robots.txt')->assertOk();
    expect(renderAt('/', '<s:seo:head />'))->not->toContain('gtm.js');
});

test('listeners and middleware of modules that are off aren\'t registered', function () {
    Features::save(['favicons', 'redirects', 'not_found', 'automatic_redirects']);
    $provider = app()->getProvider(ServiceProvider::class);
    rebootFeatures();

    $listen = (fn () => $this->listen)->call($provider);
    $middleware = (fn () => $this->middlewareGroups)->call($provider);
    $discovered = (fn () => $this->autoloadFilesFromFolder('Listeners'))->call($provider);

    expect(collect($listen)->flatten()->all())->not->toContain(RemakeFavicons::class)
        ->and($discovered)->not->toContain(RemakeFavicons::class)
        ->and($middleware)->toBe([])
        ->and((fn () => $this->subscribe)->call($provider))->toBe([]);
});
