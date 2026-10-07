<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use JothamLec\MarketingToolkit\Commands\Install;
use JothamLec\MarketingToolkit\Fieldtypes\SeoPreview;
use JothamLec\MarketingToolkit\Support\Package;
use JothamLec\MarketingToolkit\Tracking\Tracking;
use JothamLec\MarketingToolkit\UpdateScripts\AddNewBrandFields;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\YAML;

// Blueprints are written to disk and outlive a test: start each without one.
beforeEach(fn () => Blueprint::find('globals.seo')?->delete());

test('creates the SEO & brand global set and its blueprint, once', function () {
    $this->artisan('statamic:mt:install')->assertSuccessful();
    $this->artisan('statamic:mt:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')?->title())->toBe('SEO & brand')
        ->and(Blueprint::find('globals.seo')->fields()->all()->keys())->toContain('title_site_name', 'title_separator', 'publisher_type', 'og_background', 'robots_extra');
});

test('fills each empty brand field with what the site uses, so editors can see and change it', function () {
    entryIn('home', 'home', ['description' => 'We make things.']);

    $this->artisan('statamic:mt:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')->in('default')->data()->all())->toMatchArray([
        'default_description' => 'We make things.',
        'robots_disallow' => ['/cp/'],
    ])->not->toHaveKeys(['title_separator', 'title_site_name']);
});

test('never overwrites a value an editor has set', function () {
    seoGlobal(['title_separator' => '|']);

    $this->artisan('statamic:mt:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')->in('default')->data()->all())
        ->toMatchArray(['title_separator' => '|', 'robots_disallow' => ['/cp/']])
        ->not->toHaveKey('default_description');
});

test('the separator gets a space on each side however it was typed', function (?string $typed, string $title) {
    seoGlobal(['title_site_name' => true, 'title_separator' => $typed]);

    expect(metaFor(entryIn('pages', 'about'))->title)->toBe($title);
})->with([
    'trimmed' => ['|', 'About | Acme'],
    'spaced' => [' – ', 'About – Acme'],
    'empty' => [null, 'About · Acme'],
]);

test('a rerun adds what a newer version brings, in the tabs the site kept', function () {
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => [
        'brand' => ['display' => 'Brand', 'sections' => [['fields' => [['handle' => 'title_separator', 'field' => ['type' => 'text']]]]]],
        'publisher' => ['display' => 'Publisher', 'sections' => [['fields' => [['handle' => 'publisher_type', 'field' => ['type' => 'select']]]]]],
    ]])->save();

    $this->artisan('statamic:mt:install')->assertSuccessful();
    $keys = Blueprint::find('globals.seo')->fields()->all()->keys();

    expect($keys)->toContain('site_alternate_name', 'street_address', 'opening_hours', 'publisher_type')
        ->not->toContain('og_background', 'google_verification');
});

test('after an update, the new fields are added without running anything', function () {
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => [
        'brand' => ['display' => 'Brand', 'sections' => [['fields' => [['handle' => 'default_image', 'field' => ['type' => 'assets', 'container' => 'assets']]]]]],
    ]])->save();
    $script = new AddNewBrandFields(Package::NAME);

    expect($script->shouldUpdate('0.20.0', '0.19.0'))->toBeTrue();
    $script->update();

    $fields = Blueprint::find('globals.seo')->fields()->all();
    expect($fields->keys())->toContain('title_site_name', 'favicon')->not->toContain('publisher_type')
        ->and($fields->get('favicon')->get('container'))->toBe('assets');
});

test('a rerun with nothing to add says so', function () {
    $this->artisan('statamic:mt:install')->assertSuccessful();

    $this->artisan('statamic:mt:install')->expectsOutputToContain('Already installed')->assertSuccessful();
});

test('a container or tab that doesn\'t exist is refused, naming the ones that do', function () {
    $this->artisan('statamic:mt:install', ['--container' => 'missing'])->expectsOutputToContain('The containers: assets.')->assertFailed();
    $this->artisan('statamic:mt:install', ['--tab' => ['nope']])->expectsOutputToContain('The tabs: brand')->assertFailed();

    expect(Blueprint::find('globals.seo'))->toBeNull();
});

test('the defaults it fills in are named as the control panel shows them', function () {
    $this->artisan('statamic:mt:install')->expectsOutputToContain(__('marketing-toolkit::fields.brand.robots_disallow.display'))->assertSuccessful();
});

test('files in public/ that would be served instead of the addon\'s are named, and deleted when asked', function () {
    File::put(public_path('robots.txt'), "User-agent: *\nDisallow:\n");
    File::put(public_path('favicon.ico'), '');

    try {
        $this->artisan('statamic:mt:install')
            ->expectsOutputToContain('public/robots.txt, public/favicon.ico')
            ->expectsConfirmation('Delete them, so the addon serves its own?', 'no')
            ->assertSuccessful();
        expect(public_path('robots.txt'))->toBeFile();

        $this->artisan('statamic:mt:install')
            ->expectsConfirmation('Delete them, so the addon serves its own?', 'yes')
            ->assertSuccessful();
        expect(public_path('robots.txt'))->not->toBeFile()->and(public_path('favicon.ico'))->not->toBeFile();
    } finally {
        File::delete([public_path('robots.txt'), public_path('favicon.ico')]);
    }
});

test('--tab adds a whole tab a site asks for', function () {
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => [
        'brand' => ['display' => 'Brand', 'sections' => [['fields' => [['handle' => 'title_separator', 'field' => ['type' => 'text']]]]]],
    ]])->save();

    $this->artisan('statamic:mt:install', ['--tab' => ['shop']])->assertSuccessful();

    expect(Blueprint::find('globals.seo')->fields()->all()->keys())->toContain('currency', 'shipping_rates', 'return_category')
        ->not->toContain('publisher_type');
});

test('the consent regions the blueprint offers include the EEA the tracking code knows', function () {
    $regions = collect(Install::tabs('assets')['tracking']['sections'][1]['fields'])->firstWhere('handle', 'consent_regions');

    expect($regions['field']['options'])->toHaveKey(Tracking::EEA);
});

test('every label and help the blueprints name is in lang/en/fields.php', function () {
    $blueprints = [
        YAML::file(__DIR__.'/../../resources/fieldsets/seo.yaml')->parse(),
        YAML::file(__DIR__.'/../../resources/blueprints/settings.yaml')->parse(),
        Install::tabs('assets'),
        [(new ReflectionProperty(SeoPreview::class, 'title'))->getValue()],
    ];
    $keys = [];

    array_walk_recursive($blueprints, function ($value) use (&$keys) {
        if (is_string($value) && str_starts_with($value, 'marketing-toolkit::')) {
            $keys[] = $value;
        }
    });

    expect(count($keys))->toBeGreaterThan(100)
        ->and(array_values(array_filter($keys, fn (string $key) => ! Lang::has($key, 'en', false))))->toBe([]);
});

test('the brand blueprint shows in the control panel user\'s language', function () {
    app('translator')->addLines([
        'fields.brand.title_separator.display' => 'Séparateur de titre',
        'fields.brand.tabs.brand' => 'Marque',
    ], 'xx', 'marketing-toolkit');
    app()->setLocale('xx');

    $blueprint = Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => Install::tabs('assets')]);
    $field = $blueprint->field('title_separator');

    // Statamic passes the label through __() wherever it shows it: the publish
    // form (in Vue), listing columns and validation messages.
    expect($field->display())->toBe('marketing-toolkit::fields.brand.title_separator.display')
        ->and(__($field->display()))->toBe('Séparateur de titre')
        ->and($field->validationAttributes())->toBe(['title_separator' => 'Séparateur de titre'])
        ->and(__($blueprint->tabs()->get('brand')->display()))->toBe('Marque')
        ->and(__($blueprint->field('default_description')->display()))->toBe('Default description');
});
