<?php

use Illuminate\Support\Facades\Lang;
use JothamLec\MarketingToolkit\Commands\Install;
use JothamLec\MarketingToolkit\Fieldtypes\SeoPreview;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\YAML;

// Blueprints are written to disk and outlive a test: start each without one.
beforeEach(fn () => Blueprint::find('globals.seo')?->delete());

test('creates the SEO & brand global set and its blueprint, once', function () {
    $this->artisan('statamic:seo:install')->assertSuccessful();
    $this->artisan('statamic:seo:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')?->title())->toBe('SEO & brand')
        ->and(Blueprint::find('globals.seo')->fields()->all()->keys())->toContain('title_separator', 'publisher_type', 'og_background', 'robots_extra');
});

test('fills each empty brand field with what the site uses, so editors can see and change it', function () {
    entryIn('home', 'home', ['description' => 'We make things.']);

    $this->artisan('statamic:seo:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')->in('default')->data()->all())->toMatchArray([
        'title_separator' => '·',
        'default_description' => 'We make things.',
        'robots_disallow' => ['/cp/'],
    ]);
});

test('never overwrites a value an editor has set', function () {
    seoGlobal(['title_separator' => '|']);

    $this->artisan('statamic:seo:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')->in('default')->data()->all())
        ->toMatchArray(['title_separator' => '|', 'robots_disallow' => ['/cp/']])
        ->not->toHaveKey('default_description');
});

test('the separator gets a space on each side however it was typed', function (?string $typed, string $title) {
    seoGlobal(['title_separator' => $typed]);

    expect(metaFor(entryIn('pages', 'about'))->title)->toBe($title);
})->with([
    'trimmed' => ['|', 'About | Acme'],
    'spaced' => [' – ', 'About – Acme'],
    'empty' => [null, 'About · Acme'],
]);

test('--fields adds what a newer version brings, in the tabs the site kept', function () {
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => [
        'brand' => ['display' => 'Brand', 'sections' => [['fields' => [['handle' => 'title_separator', 'field' => ['type' => 'text']]]]]],
        'publisher' => ['display' => 'Publisher', 'sections' => [['fields' => [['handle' => 'publisher_type', 'field' => ['type' => 'select']]]]]],
    ]])->save();

    $this->artisan('statamic:seo:install')->assertSuccessful();
    expect(Blueprint::find('globals.seo')->fields()->all()->keys())->not->toContain('street_address');

    $this->artisan('statamic:seo:install', ['--fields' => true])->assertSuccessful();
    $keys = Blueprint::find('globals.seo')->fields()->all()->keys();

    expect($keys)->toContain('site_alternate_name', 'street_address', 'opening_hours', 'publisher_type')
        ->not->toContain('og_background', 'google_verification');
});

test('--tab adds a whole tab a site asks for', function () {
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => [
        'brand' => ['display' => 'Brand', 'sections' => [['fields' => [['handle' => 'title_separator', 'field' => ['type' => 'text']]]]]],
    ]])->save();

    $this->artisan('statamic:seo:install', ['--tab' => ['shop']])->assertSuccessful();

    expect(Blueprint::find('globals.seo')->fields()->all()->keys())->toContain('currency', 'shipping_rates', 'return_category')
        ->not->toContain('publisher_type');
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
        if (is_string($value) && str_starts_with($value, 'seo::')) {
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
    ], 'xx', 'seo');
    app()->setLocale('xx');

    $blueprint = Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => Install::tabs('assets')]);
    $field = $blueprint->field('title_separator');

    // Statamic passes the label through __() wherever it shows it: the publish
    // form (in Vue), listing columns and validation messages.
    expect($field->display())->toBe('seo::fields.brand.title_separator.display')
        ->and(__($field->display()))->toBe('Séparateur de titre')
        ->and($field->validationAttributes())->toBe(['title_separator' => 'Séparateur de titre'])
        ->and(__($blueprint->tabs()->get('brand')->display()))->toBe('Marque')
        ->and(__($blueprint->field('default_description')->display()))->toBe('Default description');
});
