<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Support\LegacySettings;
use JothamLec\MarketingToolkit\Widgets\SeoWidget;
use Statamic\Facades\Addon;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Fieldset;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Permission;
use Statamic\Widgets\VueComponent;

test('the addon registers its permissions in an SEO group', function () {
    $permissions = Permission::boot()->all()->filter(fn ($permission) => $permission->group() === 'marketing-toolkit')->map->value()->values()->all();

    expect($permissions)->toBe(['view marketing toolkit', 'manage marketing toolkit redirects', 'run marketing toolkit reports']);
});

test('the Marketing section, between Fields and Tools, opens the overview and links to Brand and Settings', function () {
    seoGlobal([]);
    GlobalSet::make('marketing')->title('Marketing settings')->save();
    $this->actingAs(cpUser(super: true));

    $nav = marketingNav();

    expect($nav->keys()->all())->toBe(['Overview', 'Reports', 'Redirects', '404s', 'Search Console', 'Brand', 'Settings'])
        ->and($nav->get('Overview')->url())->toBe(cp_route('mt.index'))
        ->and($nav->get('Brand')->url())->toBe(GlobalSet::findByHandle('seo')->in('default')->editUrl())
        ->and($nav->get('Settings')->url())->toBe(GlobalSet::findByHandle('marketing')->in('default')->editUrl());

    $sections = collect(Nav::build())->pluck('display')->all();
    expect(array_slice($sections, array_search('Fields', $sections), 3))->toBe(['Fields', 'Marketing', 'Tools']);

    $this->get(cp_route('mt.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('marketing-toolkit::Overview', false)
            ->where('siteName', 'Acme')
            ->where('global.exists', true)
            ->where('files.0', ['label' => 'Sitemap', 'url' => 'https://example.test/sitemap.xml', 'public' => false])
            ->where('global.separator', null)
            ->where('report.latest', null)
            ->where('redirects.active', 0)
            ->where('notFound.paths', 0)
            ->where('notFound.url', cp_route('mt.404s.index')));
});

test('the overview leaves out redirects for someone who cannot manage them', function () {
    $this->actingAs(cpUser(['view marketing toolkit']));

    $this->get(cp_route('mt.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('redirects', null));
});

test('without the global the overview says so', function () {
    $this->actingAs(cpUser(super: true));

    $this->get(cp_route('mt.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('global.exists', false)->where('global.url', null));
});

test('SEO is hidden from people without "view marketing toolkit"', function () {
    $this->actingAs(cpUser());

    expect(marketingNav())->toBeEmpty();
    $this->get(cp_route('mt.index'))->assertRedirect(cp_route('index'))->assertSessionHas('error');
    $this->getJson(cp_route('mt.index'))->assertForbidden();

    foreach (['mt.404s.index', 'mt.reports.index', 'mt.search-console.index'] as $route) {
        $this->getJson(cp_route($route))->assertForbidden();
    }
});

test('the dashboard widget shows its empty states until reports and 404s exist', function () {
    $this->actingAs(cpUser(['view marketing toolkit']));

    $component = (new SeoWidget)->component();

    expect($component)->toBeInstanceOf(VueComponent::class)
        ->and($component->toArray())->toBe([
            'name' => 'mt-widget',
            'props' => ['title' => 'Marketing', 'report' => null, 'notFound' => [], 'url' => cp_route('mt.index'), 'notFoundUrl' => cp_route('mt.404s.index')],
        ]);
});

test('the dashboard widget lists the most recent 404s', function () {
    $this->actingAs(cpUser(['view marketing toolkit']));

    foreach (['/a', '/b', '/c', '/d', '/e', '/f'] as $i => $path) {
        MissingPath::query()->create(['path' => $path, 'hits' => $i + 1, 'first_seen_at' => now(), 'last_seen_at' => now()->addMinutes($i)]);
    }

    expect(collect((new SeoWidget)->component()->toArray()['props']['notFound'])->pluck('path')->all())->toBe(['/f', '/e', '/d', '/c', '/b']);
});

test('the dashboard widget is left out for people without "view marketing toolkit"', function () {
    $this->actingAs(cpUser());

    expect((new SeoWidget)->component())->toBeNull();
});

test('the addon finds itself under the package name in composer.json', function () {
    $name = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true)['name'];

    // Navigation, ReportsController and ReportSettings look the addon up by this name.
    expect($name)->toBe('jotham-lec/statamic-marketing-toolkit')
        ->and(Addon::get($name)?->hasSettingsBlueprint())->toBeTrue();
});

test('the SEO names stay as they were under Co-SEO', function () {
    expect(config('marketing-toolkit.global'))->toBe('seo')
        ->and(__('marketing-toolkit::cp.seo'))->toBe('Marketing')
        ->and(Fieldset::find('marketing-toolkit::seo'))->not->toBeNull()
        ->and(view()->exists('marketing-toolkit::meta'))->toBeTrue();
});

test('Co-SEO\'s addon settings are carried over once, under the new slug', function () {
    $old = resource_path('addons/seo.yaml');
    $new = resource_path('addons/marketing-toolkit.yaml');
    File::ensureDirectoryExists(dirname($old));
    file_put_contents($old, "search_console_property: 'sc-domain:example.test'\n");

    try {
        LegacySettings::carryOver();
        expect(file_get_contents($new))->toContain('sc-domain:example.test');

        file_put_contents($old, "search_console_property: 'sc-domain:old.test'\n");
        LegacySettings::carryOver();
        expect(file_get_contents($new))->toContain('sc-domain:example.test');
    } finally {
        @unlink($old);
        @unlink($new);
    }
});

test('Co-SEO\'s addon settings in the database (Eloquent driver) are carried over, the new name\'s own winning', function () {
    Schema::create('addon_settings', function ($table) {
        $table->id();
        $table->string('addon')->unique();
        $table->json('settings')->nullable();
        $table->timestamps();
    });
    config(['statamic.eloquent-driver.addon_settings' => ['driver' => 'eloquent', 'model' => TestAddonSettings::class]]);
    TestAddonSettings::query()->create(['addon' => 'jotham-lec/statamic-co-seo', 'settings' => ['search_console_property' => 'sc-domain:example.test', 'features_off' => ['old']]]);
    TestAddonSettings::query()->create(['addon' => 'jotham-lec/statamic-marketing-toolkit', 'settings' => ['features_off' => ['sitemap']]]);

    LegacySettings::carryOver();

    expect(TestAddonSettings::query()->where('addon', 'jotham-lec/statamic-marketing-toolkit')->sole()->settings)
        ->toBe(['search_console_property' => 'sc-domain:example.test', 'features_off' => ['sitemap']]);
});

class TestAddonSettings extends Model
{
    protected $table = 'addon_settings';

    protected $guarded = [];

    protected $casts = ['settings' => 'array'];
}

test('the overview marks a file public/ serves instead of the addon\'s', function () {
    seoGlobal([]);
    File::put(public_path('robots.txt'), "User-agent: *\nDisallow:\n");

    try {
        $this->actingAs(cpUser(super: true))->get(cp_route('mt.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('files.0.public', false)
            ->where('files.1', ['label' => 'robots.txt', 'url' => 'https://example.test/robots.txt', 'public' => true]));
    } finally {
        File::delete(public_path('robots.txt'));
    }
});

test('Overview is highlighted on the overview only, not on the screens under it', function () {
    $this->actingAs(cpUser(super: true));

    $this->get(cp_route('mt.reports.show', 1));
    expect(marketingNav()->get('Overview')->isActive())->toBeFalse();

    $this->get(cp_route('mt.index'));
    expect(marketingNav()->get('Overview')->isActive())->toBeTrue();
});
