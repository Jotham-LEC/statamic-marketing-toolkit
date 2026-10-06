<?php

use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use JothamLec\Seo\Cp\Navigation;
use JothamLec\Seo\NotFound\MissingPath;
use JothamLec\Seo\Widgets\SeoWidget;
use Statamic\CP\Navigation\NavItem;
use Statamic\Facades\Addon;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;

/**
 * The CP navigation's items under Tools, keyed by name, as the user sees them.
 *
 * @return Collection<string, NavItem>
 */
function toolsNav(): Collection
{
    // AddonTestCase mocks the nav after the addon has extended the real one.
    Nav::swap(new Statamic\CP\Navigation\Nav);
    Navigation::register();

    $tools = collect(Nav::build())->firstWhere('display', 'Tools');

    return collect($tools['items'] ?? [])->keyBy(fn ($item) => $item->display());
}

test('the addon registers its permissions in an SEO group', function () {
    $permissions = Permission::boot()->all()->filter(fn ($permission) => $permission->group() === 'seo')->map->value()->values()->all();

    expect($permissions)->toBe(['view seo', 'manage seo redirects', 'run seo reports']);
});

test('Tools → SEO opens the overview and links to the brand global', function () {
    seoGlobal(['site_name' => 'Acme']);
    $this->actingAs(cpUser(super: true));

    $seo = toolsNav()->get('SEO');

    expect($seo)->not->toBeNull()
        ->and($seo->url())->toBe(cp_route('seo.index'))
        ->and(collect($seo->resolveChildren()->children())->map->display()->all())->toBe(['Reports', 'Redirects', '404s', 'Brand & defaults', 'Report settings']);

    $this->get(cp_route('seo.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('seo::Overview', false)
            ->where('siteName', 'Acme')
            ->where('global.exists', true)
            ->where('files.0', ['label' => 'Sitemap', 'url' => 'https://example.test/sitemap.xml'])
            ->where('global.separator', ' · ')
            ->where('report.latest', null)
            ->where('redirects.active', 0)
            ->where('notFound.paths', 0)
            ->where('notFound.url', cp_route('seo.404s.index')));
});

test('the overview leaves out redirects for someone who cannot manage them', function () {
    $this->actingAs(cpUser(['view seo']));

    $this->get(cp_route('seo.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('redirects', null));
});

test('without the global the overview says so', function () {
    $this->actingAs(cpUser(super: true));

    $this->get(cp_route('seo.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('global.exists', false)->where('global.url', null));
});

test('SEO is hidden from people without "view seo"', function () {
    $this->actingAs(cpUser());

    expect(toolsNav()->has('SEO'))->toBeFalse();
    $this->get(cp_route('seo.index'))->assertForbidden();
});

test('the dashboard widget shows its empty states until reports and 404s exist', function () {
    $this->actingAs(cpUser(['view seo']));

    $component = (new SeoWidget)->component();

    expect($component)->toBeInstanceOf(VueComponent::class)
        ->and($component->toArray())->toBe([
            'name' => 'seo-widget',
            'props' => ['title' => 'SEO', 'report' => null, 'notFound' => [], 'url' => cp_route('seo.index'), 'notFoundUrl' => cp_route('seo.404s.index')],
        ]);
});

test('the dashboard widget lists the most recent 404s', function () {
    $this->actingAs(cpUser(['view seo']));

    foreach (['/a', '/b', '/c', '/d', '/e', '/f'] as $i => $path) {
        MissingPath::query()->create(['path' => $path, 'hits' => $i + 1, 'first_seen_at' => now(), 'last_seen_at' => now()->addMinutes($i)]);
    }

    expect(collect((new SeoWidget)->component()->toArray()['props']['notFound'])->pluck('path')->all())->toBe(['/f', '/e', '/d', '/c', '/b']);
});

test('the dashboard widget is left out for people without "view seo"', function () {
    $this->actingAs(cpUser());

    expect((new SeoWidget)->component())->toBeNull();
});

test('the addon finds itself under the package name in composer.json', function () {
    $name = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true)['name'];

    // Navigation, ReportsController and ReportSettings look the addon up by this name.
    expect($name)->toBe('jotham-lec/statamic-co-seo')
        ->and(Addon::get($name)?->hasSettingsBlueprint())->toBeTrue();
});
