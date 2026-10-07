<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use JothamLec\MarketingToolkit\Commands\Install;
use JothamLec\MarketingToolkit\Fieldtypes\SeoPreview;
use JothamLec\MarketingToolkit\Settings;
use JothamLec\MarketingToolkit\Support\Package;
use JothamLec\MarketingToolkit\Tracking\Tracking;
use JothamLec\MarketingToolkit\UpdateScripts\AddNewBrandFields;
use JothamLec\MarketingToolkit\UpdateScripts\DropFieldDescriptions;
use JothamLec\MarketingToolkit\UpdateScripts\MoveToMarketingSettings;
use JothamLec\MarketingToolkit\UpdateScripts\RenameFromSeo;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\YAML;

// Blueprints are written to disk and outlive a test: start each without one.
beforeEach(function () {
    Blueprint::find('globals.seo')?->delete();
    Blueprint::find('globals.marketing')?->delete();
});

test('creates the Brand and Marketing settings global sets and their blueprints, once', function () {
    $this->artisan('statamic:mt:install')->assertSuccessful();
    $this->artisan('statamic:mt:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')?->title())->toBe('Brand')
        ->and(GlobalSet::findByHandle('marketing')?->title())->toBe('Marketing settings')
        ->and(Blueprint::find('globals.seo')->fields()->all()->keys())->toContain('title_site_name', 'title_separator', 'publisher_type', 'og_background')->not->toContain('gtm_id', 'robots_extra')
        ->and(Blueprint::find('globals.marketing')->tabs()->keys()->all())->toBe(['tracking', 'consent', 'leads', 'crawlers', 'features'])
        ->and(Blueprint::find('globals.marketing')->fields()->all()->keys())->toContain('gtm_id', 'consent_mode', 'conversions', 'attribution', 'robots_extra', 'ads_txt');
});

test('fills each empty brand field with what the site uses, so editors can see and change it', function () {
    entryIn('home', 'home', ['description' => 'We make things.']);

    $this->artisan('statamic:mt:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')->in('default')->data()->all())->toMatchArray(['default_description' => 'We make things.'])
        ->not->toHaveKeys(['title_separator', 'title_site_name', 'robots_disallow'])
        ->and(GlobalSet::findByHandle('marketing')->in('default')->data()->all())->toMatchArray(['robots_disallow' => ['/cp/']]);
});

test('never overwrites a value an editor has set', function () {
    seoGlobal(['title_separator' => '|']);

    $this->artisan('statamic:mt:install')->assertSuccessful();

    expect(GlobalSet::findByHandle('seo')->in('default')->data()->all())
        ->toMatchArray(['title_separator' => '|'])
        ->not->toHaveKey('default_description')
        ->and(GlobalSet::findByHandle('marketing')->in('default')->data()->all())->toMatchArray(['robots_disallow' => ['/cp/']]);
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

test('a field the site removed stays removed through later updates', function () {
    $this->artisan('statamic:mt:install')->assertSuccessful();
    // The developer removes a field from each, and 0.21's Features switch for leads is missing, as on a 0.20 site.
    $remove = function (string $handle, string ...$fields) {
        $blueprint = Blueprint::find("globals.{$handle}");
        $contents = $blueprint->contents();
        foreach ($contents['tabs'] as $tab => $config) {
            foreach ($config['sections'] as $index => $section) {
                $contents['tabs'][$tab]['sections'][$index]['fields'] = array_values(array_filter($section['fields'], fn (array $field) => ! in_array($field['handle'], $fields, true)));
            }
        }
        $blueprint->setContents($contents)->save();
    };
    $remove('seo', 'twitter_handle');
    $remove('marketing', 'linkedin_partner_id', 'feature_leads');
    $script = new AddNewBrandFields(Package::NAME);

    // Nothing came in 0.21.4, so a patch update doesn't run at all.
    expect($script->shouldUpdate('0.21.4.0', '0.21.3.0'))->toBeFalse()
        ->and($script->shouldUpdate('0.21.4', '0.21.0'))->toBeFalse()
        ->and($script->shouldUpdate('0.21.4.0', 'dev-main'))->toBeFalse();

    // From 0.20, only what 0.21 brought comes back: the Features switches, not the site's removals.
    expect($script->shouldUpdate('0.21.4.0', '0.20.0.0'))->toBeTrue();
    $script->update();
    $script->update();

    expect(Blueprint::find('globals.seo')->fields()->all()->keys())->not->toContain('twitter_handle')
        ->and(Blueprint::find('globals.marketing')->fields()->all()->keys())->toContain('feature_leads')->not->toContain('linkedin_partner_id');
});

test('updating to 0.22 adds the Front-end toolbar switch to the Features tab, and nothing else', function () {
    $this->artisan('statamic:mt:install')->assertSuccessful();
    $blueprint = Blueprint::find('globals.marketing');
    $contents = $blueprint->contents();
    foreach ($contents['tabs'] as $tab => $config) {
        foreach ($config['sections'] as $index => $section) {
            $contents['tabs'][$tab]['sections'][$index]['fields'] = array_values(array_filter($section['fields'], fn (array $field) => ! in_array($field['handle'], ['feature_toolbar', 'gtm_id'], true)));
        }
    }
    $blueprint->setContents($contents)->save();
    $script = new AddNewBrandFields(Package::NAME);

    expect($script->shouldUpdate('0.22.0.0', '0.21.4.0'))->toBeTrue();
    $script->update();

    expect(Blueprint::find('globals.marketing')->fields()->all()->keys())->toContain('feature_toolbar')->not->toContain('gtm_id');
});

test('every field mt:install writes is in 0.20 or listed by the version that brought it', function () {
    $fields = collect(['seo', 'marketing'])->flatMap(fn (string $file) => collect(Install::tabs('assets', $file))
        ->flatMap(fn (array $tab) => $tab['sections'])->flatMap(fn (array $section) => $section['fields'])->pluck('handle'));
    $before = file(__DIR__.'/../fixtures/fields-0.20.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    expect($fields->diff($before)->diff(collect(AddNewBrandFields::FIELDS)->flatten())->values()->all())->toBe([]);
});

test('a collection with a route and no blueprint yet gets one with an SEO tab', function () {
    Collection::make('drafts')->save();

    $this->artisan('statamic:mt:install')->expectsOutputToContain('SEO tab added to the blueprints of')->assertSuccessful();

    $pages = Collection::find('pages')->entryBlueprint();
    expect($pages->hasField('seo'))->toBeTrue()
        ->and($pages->tabs()->keys()->all())->toContain('main', 'seo')
        ->and(resource_path('blueprints/collections/pages/page.yaml'))->toBeFile()
        // No route, no pages: nothing to add SEO fields to.
        ->and(resource_path('blueprints/collections/drafts'))->not->toBeDirectory();

    Collection::find('drafts')->delete();
});

test('--no-blueprints leaves the collections alone', function () {
    $this->artisan('statamic:mt:install', ['--no-blueprints' => true])->assertSuccessful();

    expect(resource_path('blueprints/collections'))->not->toBeDirectory();
});

test('a blueprint of the site\'s own without the SEO tab is named, never changed, so a removed tab stays removed', function () {
    $this->artisan('statamic:mt:install')->assertSuccessful();
    $page = resource_path('blueprints/collections/pages/page.yaml');
    $contents = YAML::file($page)->parse();
    unset($contents['tabs']['seo']);
    File::put($page, YAML::dump($contents));
    $before = File::get($page);

    $this->artisan('statamic:mt:install')->expectsOutputToContain('These blueprints have no SEO tab: Pages (Page)')->assertSuccessful();

    expect(File::get($page))->toBe($before);
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
    $regions = collect(Install::tabs('assets', 'marketing')['consent']['sections'][0]['fields'])->firstWhere('handle', 'consent_regions');

    expect($regions['field']['options'])->toHaveKey(Tracking::EEA);
});

test('every label and help the blueprints name is in lang/en/fields.php', function () {
    $blueprints = [
        YAML::file(__DIR__.'/../../resources/fieldsets/seo.yaml')->parse(),
        YAML::file(__DIR__.'/../../resources/blueprints/settings.yaml')->parse(),
        Install::tabs('assets'),
        Install::tabs('assets', 'marketing'),
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

test('an update moves tracking, consent, leads and crawlers from SEO & brand to Marketing settings', function () {
    multisite();
    // SEO & brand as 0.20 had it: every tab in one set, and a field of the site's own on the Tracking tab.
    $tabs = [...Install::tabs('assets'), ...Install::tabs('assets', 'marketing')];
    $tabs['tracking']['sections'][0]['fields'][] = ['handle' => 'hotjar_id', 'field' => ['type' => 'text']];
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => $tabs])->save();
    $set = GlobalSet::make('seo')->title('SEO & brand')->sites(['default' => null, 'cothinking' => 'default']);
    $set->save();
    $set->in('default')->data(['title_separator' => '|', 'gtm_id' => 'GTM-ABC123', 'robots_disallow' => ['/cp/'], 'hotjar_id' => '42'])->save();
    $set->in('cothinking')->data(['consent_mode' => true])->save();
    $script = new MoveToMarketingSettings(Package::NAME);

    expect($script->shouldUpdate('0.21.0', '0.20.0'))->toBeTrue();
    $script->update();

    $brand = GlobalSet::findByHandle('seo');
    $marketing = GlobalSet::findByHandle('marketing');
    expect($brand->title())->toBe('Brand')
        ->and($brand->in('default')->data()->all())->toBe(['title_separator' => '|', 'hotjar_id' => '42'])
        ->and($marketing->title())->toBe('Marketing settings')
        ->and($marketing->origins()->all())->toBe(['default' => null, 'cothinking' => 'default'])
        ->and($marketing->in('default')->data()->all())->toBe(['gtm_id' => 'GTM-ABC123', 'robots_disallow' => ['/cp/']])
        ->and($marketing->in('cothinking')->data()->all())->toBe(['consent_mode' => true])
        ->and(Blueprint::find('globals.seo')->tabs()->keys()->all())->toBe(['brand', 'publisher', 'shop', 'share_cards', 'tracking'])
        ->and(Blueprint::find('globals.seo')->fields()->all()->keys()->all())->toContain('hotjar_id')->not->toContain('gtm_id', 'robots_extra')
        ->and(Blueprint::find('globals.marketing')->fields()->all()->keys())->toContain('gtm_id', 'consent_mode', 'robots_extra')
        ->and($script->shouldUpdate('0.21.0', '0.20.0'))->toBeFalse();

    // Read from their new place, as before.
    expect(app(Settings::class)->string('gtm_id'))->toBe('GTM-ABC123');
});

test('after 0.21, a field the site puts back in Brand stays there', function () {
    seoGlobal(['gtm_id' => 'GTM-ABC123']);
    $brand = Blueprint::find('globals.seo');
    $tabs = $brand->contents()['tabs'] ?? [];
    $tabs['brand']['sections'][0]['fields'][] = ['handle' => 'gtm_id', 'field' => ['type' => 'text']];
    $brand->setContents(['tabs' => $tabs])->save();
    $script = new MoveToMarketingSettings(Package::NAME);

    expect($script->shouldUpdate('0.21.4.0', '0.21.3.0'))->toBeFalse()
        ->and($script->shouldUpdate('0.21.4.0', 'dev-main'))->toBeFalse()
        ->and($script->shouldUpdate('0.21.4.0', '0.20.0.0'))->toBeTrue();
});

test('a site set up before the move reads its tracking from SEO & brand until it updates', function () {
    seoGlobal(['gtm_id' => 'GTM-OLD123']);

    expect(app(Settings::class)->string('gtm_id'))->toBe('GTM-OLD123');
});

test('values left in Brand after the blueprints moved are moved too, never over a newer one', function () {
    seoGlobal(['title_separator' => '|', 'gtm_id' => 'GTM-OLD123', 'ga4_id' => 'G-OLD12345']);
    Blueprint::make('marketing')->setNamespace('globals')->setContents(['tabs' => Install::tabs('assets', 'marketing')])->save();
    $set = GlobalSet::make('marketing')->title('Marketing settings');
    $set->save();
    $set->in('default')->data(['gtm_id' => 'GTM-NEW123'])->save();
    $script = new MoveToMarketingSettings(Package::NAME);

    // The blueprints have moved already: only the values tell.
    expect(Blueprint::find('globals.seo')->fields()->all()->keys())->not->toContain('gtm_id')
        ->and(app(Settings::class)->string('gtm_id'))->toBe('GTM-NEW123')
        ->and($script->shouldUpdate('0.21.0', '0.20.0'))->toBeTrue();
    $script->update();

    expect(GlobalSet::findByHandle('seo')->in('default')->data()->all())->toBe(['title_separator' => '|'])
        ->and(GlobalSet::findByHandle('marketing')->in('default')->data()->all())->toBe(['gtm_id' => 'GTM-NEW123', 'ga4_id' => 'G-OLD12345'])
        ->and($script->shouldUpdate('0.21.0', '0.20.0'))->toBeFalse();
});

test('an update takes the addon\'s old field descriptions out of the site\'s blueprints, keeping the site\'s own', function () {
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => ['brand' => ['sections' => [[
        'instructions' => 'marketing-toolkit::fields.brand.sections.publisher.instructions',
        'fields' => [
            ['handle' => 'title_separator', 'field' => ['type' => 'text', 'display' => 'marketing-toolkit::fields.brand.title_separator.display', 'instructions' => 'marketing-toolkit::fields.brand.title_separator.instructions']],
            ['handle' => 'slogan', 'field' => ['type' => 'text', 'instructions' => 'Our own words.']],
        ],
    ]]]]])->save();
    $script = new DropFieldDescriptions(Package::NAME);

    expect($script->shouldUpdate('0.21.0', '0.20.0'))->toBeTrue();
    $script->update();

    $section = Blueprint::find('globals.seo')->contents()['tabs']['brand']['sections'][0];
    expect($section)->not->toHaveKey('instructions')
        ->and($section['fields'][0]['field'])->toBe(['type' => 'text', 'display' => 'marketing-toolkit::fields.brand.title_separator.display'])
        ->and($section['fields'][1]['field']['instructions'])->toBe('Our own words.')
        ->and($script->shouldUpdate('0.21.0', '0.20.0'))->toBeFalse();
});

test('an update takes out the old field descriptions still under their 0.19 `seo::` names', function () {
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => ['brand' => ['sections' => [['fields' => [
        ['handle' => 'title_separator', 'field' => ['type' => 'text', 'display' => 'seo::fields.brand.title_separator.display', 'instructions' => 'seo::fields.brand.title_separator.instructions']],
    ]]]]]])->save();
    $script = new DropFieldDescriptions(Package::NAME);

    expect($script->shouldUpdate('0.21.0', '0.18.0'))->toBeTrue();
    $script->update();

    expect(Blueprint::find('globals.seo')->contents()['tabs']['brand']['sections'][0]['fields'][0]['field'])
        ->toBe(['type' => 'text', 'display' => 'seo::fields.brand.title_separator.display'])
        ->and($script->shouldUpdate('0.21.0', '0.18.0'))->toBeFalse();
});

test('updating from 0.21.2 or later leaves the site\'s blueprints alone, unless asked from 0.21.1', function () {
    // A description that names a key the addon no longer has: taken out once, by the update to 0.21.2.
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => ['brand' => ['sections' => [['fields' => [
        ['handle' => 'title_separator', 'field' => ['type' => 'text', 'instructions' => 'marketing-toolkit::fields.brand.title_separator.instructions']],
    ]]]]]])->save();
    $script = new DropFieldDescriptions(Package::NAME);

    expect($script->shouldUpdate('0.21.6.0', '0.21.5.0'))->toBeFalse()
        ->and($script->shouldUpdate('0.21.2.0', '0.21.2.0'))->toBeFalse()
        ->and($script->shouldUpdate('0.21.6.0', 'dev-main'))->toBeFalse()
        // `php please updates:run 0.21.1`, as upgrading.md says for a site that missed it.
        ->and($script->shouldUpdate('0.21.6.0', '0.21.1'))->toBeTrue();
});

test('a site that swapped Co-SEO for this package without updates:run loses the old descriptions on its next update', function () {
    // Statamic skips a package missing from the old composer.lock, so the swap itself ran nothing.
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => ['brand' => ['sections' => [['fields' => [
        ['handle' => 'title_separator', 'field' => ['type' => 'text', 'display' => 'seo::fields.brand.title_separator.display', 'instructions' => 'seo::fields.brand.title_separator.instructions']],
    ]]]]]])->save();
    $drop = new DropFieldDescriptions(Package::NAME);
    $rename = new RenameFromSeo(Package::NAME);

    expect($drop->shouldUpdate('0.21.6.0', '0.21.5.0'))->toBeTrue()
        ->and($rename->shouldUpdate('0.21.6.0', '0.21.5.0'))->toBeTrue();
    $drop->update();
    $rename->update();

    expect(Blueprint::find('globals.seo')->contents()['tabs']['brand']['sections'][0]['fields'][0]['field'])
        ->toBe(['type' => 'text', 'display' => 'marketing-toolkit::fields.brand.title_separator.display'])
        ->and($drop->shouldUpdate('0.21.7.0', '0.21.6.0'))->toBeFalse()
        ->and($rename->shouldUpdate('0.21.7.0', '0.21.6.0'))->toBeFalse();
});

test('a site updating from 0.18 has no old field descriptions left after the rename', function () {
    // Statamic asks every script whether it should run before any runs, so this one runs before the rename.
    Blueprint::make('seo')->setNamespace('globals')->setContents(['tabs' => ['brand' => ['sections' => [[
        'instructions' => 'seo::fields.brand.sections.publisher.instructions',
        'fields' => [
            ['handle' => 'title_separator', 'field' => ['type' => 'text', 'display' => 'seo::fields.brand.title_separator.display', 'instructions' => 'seo::fields.brand.title_separator.instructions']],
            ['handle' => 'publisher_type', 'field' => ['type' => 'select', 'display' => 'seo::fields.brand.publisher_type.display', 'instructions' => 'seo::fields.brand.publisher_type.instructions']],
        ],
    ]]]]])->save();
    $path = Blueprint::find('globals.seo')->path();

    $drop = new DropFieldDescriptions(Package::NAME);
    $rename = new RenameFromSeo(Package::NAME);
    expect($drop->shouldUpdate('0.21.1', '0.18.0'))->toBeTrue()
        ->and($rename->shouldUpdate('0.21.1', '0.18.0'))->toBeTrue();
    $drop->update();
    $rename->update();

    expect(File::get($path))->toContain('marketing-toolkit::fields.brand.title_separator.display')
        ->not->toContain('instructions:')->not->toContain('seo::');
});
