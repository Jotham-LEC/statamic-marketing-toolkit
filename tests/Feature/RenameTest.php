<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use JothamLec\MarketingToolkit\Support\Edition;
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
        $layout = resource_path('views/rename-layout.antlers.html') => "<head>\n    {{ seo:head }}\n</head>\n<body>{{seo:body}}<s:seo:meta /></body>\n",
        $cp = config_path('statamic/cp.php') => "<?php\n\nreturn ['widgets' => [['type' => 'seo', 'width' => 100], ['type' => 'collection']]];\n",
        $config = config_path('seo.php') => "<?php\n\nreturn ['global' => 'seo'];\n",
        $lang = lang_path('vendor/seo/en/cp.php') => "<?php\n\nreturn [];\n",
    ];

    try {
        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $contents);
        }
        Role::make('editor')->permissions(['access cp', 'view seo', 'manage seo redirects'])->save();

        $script = new RenameFromSeo(Edition::PACKAGE);
        expect($script->shouldUpdate('0.20.0', '0.19.0'))->toBeTrue();
        $script->update();

        expect(File::get($page))->toContain('import: marketing-toolkit::seo')->toContain('type: mt_preview')->not->toContain('seo::')
            ->and(File::get($brand))->toContain("'marketing-toolkit::fields.brand.tracking_overlap.display'")->toContain('if: mtTrackingOverlap')
            ->toContain("'aardvark-seo::label'")
            ->and(File::get($layout))->toBe("<head>\n    {{ mt:head }}\n</head>\n<body>{{mt:body}}<s:mt:meta /></body>\n")
            ->and(File::get($cp))->toContain("['type' => 'mt', 'width' => 100], ['type' => 'collection']")
            ->and(config_path('seo.php'))->not->toBeFile()
            ->and(config_path('marketing-toolkit.php'))->toBeFile()
            ->and(lang_path('vendor/marketing-toolkit/en/cp.php'))->toBeFile()
            ->and(Role::find('editor')->permissions()->all())->toBe(['access cp', 'view marketing toolkit', 'manage marketing toolkit redirects']);

        // Run again (each update runs it), it changes nothing.
        $before = File::get($brand);
        $script->update();
        expect(File::get($brand))->toBe($before);
    } finally {
        File::delete([...array_keys($files), config_path('marketing-toolkit.php')]);
        File::deleteDirectory(lang_path('vendor'));
        Role::find('editor')?->delete();
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
