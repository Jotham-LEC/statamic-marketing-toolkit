<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use JothamLec\MarketingToolkit\NotFound\MissingPath;
use JothamLec\MarketingToolkit\Redirects\Redirect;
use JothamLec\MarketingToolkit\Reports\Runner;
use Statamic\Facades\YAML;

/**
 * Every `seo::` key the addon's PHP, Vue and YAML name.
 *
 * @return list<string>
 */
function translationKeysUsed(): array
{
    $root = __DIR__.'/../..';
    $files = collect([
        ...File::allFiles($root.'/src'),
        ...File::allFiles($root.'/resources/js'),
        ...File::allFiles($root.'/resources/views'),
        ...File::allFiles($root.'/resources/fieldsets'),
        ...File::allFiles($root.'/resources/blueprints'),
        ...File::allFiles($root.'/resources/install'),
    ]);

    return $files->flatMap(function ($file) {
        // Quoted in PHP and Vue, bare in YAML.
        preg_match_all('/(?<![\w:])seo::[a-z_]+(?:\.[a-z0-9_]+)+/', $file->getContents(), $matches);

        return $matches[0];
    })->unique()->sort()->values()->all();
}

test('every key the code and blueprints use is in lang/en', function () {
    $keys = translationKeysUsed();
    $missing = array_values(array_filter($keys, fn (string $key) => ! Lang::has($key, 'en', false)));

    expect(count($keys))->toBeGreaterThan(100)
        ->and($missing)->toBe([]);
});

test('lang/en has no key left unused', function () {
    $used = translationKeysUsed();
    $defined = collect(File::files(__DIR__.'/../../lang/en'))
        ->flatMap(fn ($file) => array_keys(Arr::dot(['seo::'.$file->getFilenameWithoutExtension() => require $file->getPathname()])))
        ->all();

    // Built from parts at run time: a rule's handle, a report check's message, a Pro feature.
    $dynamic = fn (string $key) => str_starts_with($key, 'seo::reports.') || preg_match('/^seo::cp\.pro\.(sites|reports|not_found|search_console)\.(title|body)$/', $key) || str_starts_with($key, 'seo::cp.tracking.names.') || str_starts_with($key, 'seo::fields.attribution.') || str_starts_with($key, 'seo::cp.features.');

    expect(array_values(array_filter($defined, fn (string $key) => ! in_array($key, $used, true) && ! $dynamic($key))))->toBe([]);
});

test('no screen shows a raw translation key', function () {
    seoGlobal([]);
    $this->actingAs(cpUser(super: true));

    $redirect = Redirect::query()->create(['source' => '/old', 'target' => '/new', 'status' => 301, 'active' => true]);
    MissingPath::query()->create(['path' => '/missing', 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);
    entryIn('pages', 'about');
    $report = app(Runner::class)->runToEnd(app(Runner::class)->start());

    $screens = [
        cp_route('seo.index'),
        cp_route('seo.redirects.index'),
        cp_route('seo.redirects.listing'),
        cp_route('seo.redirects.create'),
        cp_route('seo.redirects.edit', $redirect),
        cp_route('seo.404s.index'),
        cp_route('seo.404s.listing'),
        cp_route('seo.reports.index'),
        cp_route('seo.reports.show', $report),
        cp_route('seo.reports.pages', $report),
        cp_route('seo.search-console.index'),
    ];

    foreach ($screens as $url) {
        $response = $this->get($url, ['X-Inertia' => 'true', 'Accept' => 'application/json'])->assertOk();

        expect($response->getContent())->not->toContain('seo::', $url);
    }
});

test('a control panel screen, a nav item and an error message come out in the user\'s language', function () {
    config(['app.locale' => 'xx', 'app.fallback_locale' => 'en']);
    app('translator')->addLines([
        'cp.overview.files.sitemap' => 'XX Sitemap',
        'cp.nav.redirects' => 'XX Redirects',
        'cp.search_console.messages.add_first' => 'XX Add the key first.',
    ], 'xx', 'seo');
    app()->setLocale('xx');

    $this->actingAs(cpUser(super: true));

    $this->get(cp_route('seo.index'))->assertInertia(fn ($page) => $page->where('files.0.label', 'XX Sitemap'));
    $this->postJson(cp_route('seo.search-console.check'))->assertJson(['ok' => false, 'message' => 'XX Add the key first.']);

    expect(collect(toolsNav()->get('SEO')->resolveChildren()->children())->map->display()->all())->toContain('XX Redirects');
});

test('"Page N" is in the page\'s own language', function () {
    multilang();
    app('translator')->addLines(['frontend.page' => 'Page :n (fr)'], 'fr', 'seo');
    $french = translationOf(entryIn('pages', 'essays'), 'fr', 'essais');

    expect(metaFor($french, '/fr/essais?page=2')->title)->toEndWith('Page 2 (fr)');
});

test('the fieldset and settings blueprint name keys, not English', function () {
    $displays = collect([
        YAML::file(__DIR__.'/../../resources/fieldsets/seo.yaml')->parse(),
        YAML::file(__DIR__.'/../../resources/blueprints/settings.yaml')->parse(),
    ])->flatMap(fn (array $yaml) => collect(Arr::dot($yaml))
        ->filter(fn ($value, string $key) => preg_match('/\.(display|instructions)$/', $key) === 1)
        ->values());

    expect($displays)->not->toBeEmpty()
        ->and($displays->reject(fn ($value) => str_starts_with((string) $value, 'seo::'))->values()->all())->toBe([]);
});
