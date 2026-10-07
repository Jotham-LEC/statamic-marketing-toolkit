<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use JothamLec\MarketingToolkit\Support\Package;
use JothamLec\MarketingToolkit\UpdateScripts\RenameFromSeo;
use Statamic\Facades\Role;

/**
 * Up to 0.19 the addon's names were `seo`: config/seo.php, `seo::` namespaces,
 * the `seo` tag, `seo_*` tables, `view seo` permissions. A site moves to the
 * new names by itself: its files on `composer update`, its server on `migrate`.
 */
test('the update script renames the seo names in the site\'s own files and roles', function () {
    $files = [
        $page = resource_path('blueprints/collections/pages/page.yaml') => "tabs:\n  seo:\n    sections:\n      -\n        fields:\n          -\n            import: seo::seo\n          -\n            handle: preview\n            field:\n              type: seo_preview\n",
        $brand = resource_path('blueprints/globals/seo.yaml') => "tabs:\n  tracking:\n    sections:\n      -\n        fields:\n          -\n            handle: tracking_overlap\n            field: { type: section, display: 'seo::fields.brand.tracking_overlap.display', if: seoTrackingOverlap }\n          -\n            handle: aardvark\n            field: { type: text, display: 'aardvark-seo::label' }\n",
        $layout = resource_path('views/rename-layout.antlers.html') => "<head>\n    {{ seo:head }}\n    {{ seo:meta title=\"Hi\" }}\n</head>\n<body>{{seo:body}}<s:seo:meta /></body>\n",
        // Brand's own values (the global is still `seo`) aren't the addon's tags.
        $brandValues = resource_path('views/partials/rename-brand.antlers.html') => "{{ seo:site_name }} {{ seo:favicon }} {{ seo:logo:url }} {{ seo:gtm_id }} {{ seo:meta_title }} {{ seo:header }}\n",
        $blade = resource_path('views/rename-layout.blade.php') => "<head><s:seo:head />{!! Statamic::tag('seo:favicons') !!}</head>\n<body><s:seo:body /> {{ \$seo['site_name'] }} <s:seo:site_name /></body>\n",
        $cp = config_path('statamic/cp.php') => "<?php\n\nreturn ['widgets' => [['type' => 'seo', 'width' => 100], ['type' => 'collection']]];\n",
        $config = config_path('seo.php') => "<?php\n\nuse JothamLec\\MarketingToolkit\\SiteSeo;\n\nreturn ['class' => SiteSeo::class, 'global' => 'seo'];\n",
        $lang = lang_path('vendor/seo/en/fields.php') => "<?php\n\nreturn [];\n",
    ];

    try {
        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $contents);
        }
        Role::make('editor')->permissions(['access cp', 'view seo', 'manage seo redirects'])->save();

        $script = new RenameFromSeo(Package::NAME);
        expect($script->shouldUpdate('0.20.0', '0.19.0'))->toBeTrue();
        $script->update();

        expect(File::get($page))->toContain('import: marketing-toolkit::seo')->toContain('type: mt_preview')->not->toContain('seo::')
            ->and(File::get($brand))->toContain("'marketing-toolkit::fields.brand.tracking_overlap.display'")->toContain('if: mtTrackingOverlap')
            ->toContain("'aardvark-seo::label'")
            ->and(File::get($layout))->toBe("<head>\n    {{ mt:head }}\n    {{ mt:meta title=\"Hi\" }}\n</head>\n<body>{{mt:body}}<s:mt:meta /></body>\n")
            ->and(File::get($brandValues))->toBe($files[$brandValues])
            ->and(File::get($blade))->toBe("<head><s:mt:head />{!! Statamic::tag('mt:favicons') !!}</head>\n<body><s:mt:body /> {{ \$seo['site_name'] }} <s:seo:site_name /></body>\n")
            ->and(File::get($cp))->toContain("['type' => 'mt', 'width' => 100], ['type' => 'collection']")
            ->and(config_path('seo.php'))->not->toBeFile()
            ->and(config_path('marketing-toolkit.php'))->toBeFile()
            ->and(lang_path('vendor/marketing-toolkit/en/fields.php'))->toBeFile()
            ->and(Role::find('editor')->permissions()->all())->toBe(['access cp', 'view marketing toolkit', 'manage marketing toolkit redirects']);

        // Done once: the next update doesn't run it, and running it again by hand changes nothing.
        $before = array_map(fn (string $path) => File::exists($path) ? File::get($path) : null, array_keys($files));
        expect($script->shouldUpdate('0.21.4.0', '0.21.3.0'))->toBeFalse();
        $script->update();
        expect(array_map(fn (string $path) => File::exists($path) ? File::get($path) : null, array_keys($files)))->toBe($before);
    } finally {
        File::delete([...array_keys($files), config_path('marketing-toolkit.php')]);
        File::deleteDirectory(resource_path('views/partials'));
        File::deleteDirectory(lang_path('vendor'));
        Role::find('editor')?->delete();
    }
});

test('updating from 0.20 or later leaves the templates alone, Brand\'s values and all', function () {
    $layout = resource_path('views/rename-later.antlers.html');
    File::put($layout, "{{ seo:site_name }} {{ seo:head }}\n");

    try {
        $script = new RenameFromSeo(Package::NAME);

        expect($script->shouldUpdate('0.21.4.0', '0.21.3.0'))->toBeFalse()
            ->and($script->shouldUpdate('0.21.4.0', '0.20.0.0'))->toBeFalse()
            ->and($script->shouldUpdate('0.21.4.0', 'dev-main'))->toBeFalse()
            ->and($script->shouldUpdate('0.21.4.0', '0.19.0.0'))->toBeTrue()
            ->and(File::get($layout))->toBe("{{ seo:site_name }} {{ seo:head }}\n");
    } finally {
        File::delete($layout);
    }
});

test('another package\'s config/seo.php and translations stay where they are', function () {
    // ralphjsmit/laravel-seo publishes a config/seo.php.
    File::put(config_path('seo.php'), "<?php\n\nreturn ['site_name' => 'Acme', 'sitemap' => null];\n");
    File::ensureDirectoryExists(lang_path('vendor/seo/en'));
    File::put(lang_path('vendor/seo/en/messages.php'), "<?php\n\nreturn [];\n");

    try {
        $script = new RenameFromSeo(Package::NAME);

        expect($script->shouldUpdate('0.21.4.0', '0.21.3.0'))->toBeFalse();
        $script->update();

        expect(config_path('seo.php'))->toBeFile()
            ->and(config_path('marketing-toolkit.php'))->not->toBeFile()
            ->and(lang_path('vendor/seo/en/messages.php'))->toBeFile()
            ->and(lang_path('vendor/marketing-toolkit'))->not->toBeDirectory();
    } finally {
        File::delete(config_path('seo.php'));
        File::deleteDirectory(lang_path('vendor'));
    }
});

test('a site with a seo tag of its own keeps its templates', function () {
    $layout = resource_path('views/rename-own-tag.antlers.html');
    File::put($layout, "{{ seo:head }}\n");
    app('statamic.tags')['seo'] = RenameFromSeo::class;

    try {
        (new RenameFromSeo(Package::NAME))->update();

        expect(File::get($layout))->toBe("{{ seo:head }}\n");
    } finally {
        unset(app('statamic.tags')['seo']);
        File::delete($layout);
    }
});

test('a site that swapped Co-SEO for this package without updates:run is renamed on its next update', function () {
    // Statamic skips a package missing from the old composer.lock, so the swap itself ran nothing.
    $page = resource_path('blueprints/collections/pages/page.yaml');
    File::ensureDirectoryExists(dirname($page));
    File::put($page, "tabs:\n  seo:\n    sections:\n      -\n        fields:\n          -\n            import: seo::seo\n");

    try {
        $script = new RenameFromSeo(Package::NAME);

        expect($script->shouldUpdate('0.21.4.0', '0.21.3.0'))->toBeTrue();
        $script->update();

        expect(File::get($page))->toContain('import: marketing-toolkit::seo')
            ->and($script->shouldUpdate('0.21.4.0', '0.21.3.0'))->toBeFalse();
    } finally {
        File::delete($page);
    }
});

test('the migration renames the tables, the keys stored in reports, and moves an uploaded Search Console key', function () {
    $migration = require __DIR__.'/../../database/migrations/2026_10_10_000001_rename_seo_tables_to_mt.php';
    $migration->down();
    expect(Schema::hasTable('seo_reports'))->toBeTrue()->and(Schema::hasTable('mt_reports'))->toBeFalse();

    $id = DB::table('seo_reports')->insertGetId(['settings' => '[]', 'summary' => json_encode(['rules' => ['title_length' => ['label' => 'seo::reports.rules.title_length']]]), 'error' => 'seo::reports.messages.stopped']);
    DB::table('seo_report_pages')->insert(['report_id' => $id, 'url' => '/', 'content_type' => 'entry', 'content_id' => 'home', 'results' => json_encode(['title_length' => ['message' => 'seo::reports.messages.title_missing']])]);
    File::ensureDirectoryExists(storage_path('app/private/seo'));
    File::put(storage_path('app/private/seo/search-console-key.json'), 'encrypted');

    $migration->up();

    expect(Schema::hasTable('seo_reports'))->toBeFalse()
        ->and(DB::table('mt_reports')->find($id)->error)->toBe('marketing-toolkit::reports.messages.stopped')
        ->and(json_decode(DB::table('mt_reports')->find($id)->summary, true)['rules']['title_length']['label'])->toBe('marketing-toolkit::reports.rules.title_length')
        ->and(DB::table('mt_report_pages')->where('report_id', $id)->value('results'))->toContain('marketing-toolkit::reports.messages.title_missing')
        ->and(File::get(storage_path('app/private/marketing-toolkit/search-console-key.json')))->toBe('encrypted')
        ->and(storage_path('app/private/seo'))->not->toBeDirectory();

    File::deleteDirectory(storage_path('app/private/marketing-toolkit'));
});
