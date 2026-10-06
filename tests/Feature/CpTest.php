<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Support\LegacySettings;
use JothamLec\MarketingToolkit\Widgets\SeoWidget;
use Statamic\Facades\Addon;
use Statamic\Facades\Fieldset;
use Statamic\Facades\Permission;
use Statamic\Widgets\VueComponent;

test('the addon registers its permissions in an SEO group', function () {
    $permissions = Permission::boot()->all()->filter(fn ($permission) => $permission->group() === 'seo')->map->value()->values()->all();

    expect($permissions)->toBe(['view seo', 'manage seo redirects', 'run seo reports']);
});

test('Tools → SEO opens the overview and links to the brand global', function () {
    seoGlobal([]);
    $this->actingAs(cpUser(super: true));

    $seo = toolsNav()->get('SEO');

    expect($seo)->not->toBeNull()
        ->and($seo->url())->toBe(cp_route('seo.index'))
        ->and(collect($seo->resolveChildren()->children())->map->display()->all())->toBe(['Reports', 'Redirects', '404s', 'Search Console', 'Brand & defaults', 'Report settings', 'Features']);

    $this->get(cp_route('seo.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('seo::Overview', false)
            ->where('siteName', 'Acme')
            ->where('global.exists', true)
            ->where('files.0', ['label' => 'Sitemap', 'url' => 'https://example.test/sitemap.xml'])
            ->where('global.separator', null)
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
    expect($name)->toBe('jotham-lec/statamic-marketing-toolkit')
        ->and(Addon::get($name)?->hasSettingsBlueprint())->toBeTrue();
});

test('the SEO names stay as they were under Co-SEO', function () {
    expect(config('seo.global'))->toBe('seo')
        ->and(__('seo::cp.seo'))->toBe('SEO')
        ->and(Fieldset::find('seo::seo'))->not->toBeNull()
        ->and(view()->exists('seo::meta'))->toBeTrue();
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
