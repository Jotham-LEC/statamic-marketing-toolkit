<?php

use Illuminate\Support\Facades\Route;
use JothamLec\MarketingToolkit\Http\Controllers\CP\ActionController;
use JothamLec\MarketingToolkit\Http\Controllers\CP\NotFoundController;
use JothamLec\MarketingToolkit\Http\Controllers\CP\OverviewController;
use JothamLec\MarketingToolkit\Http\Controllers\CP\PreviewController;
use JothamLec\MarketingToolkit\Http\Controllers\CP\RedirectsController;
use JothamLec\MarketingToolkit\Http\Controllers\CP\ReportsController;
use JothamLec\MarketingToolkit\Http\Controllers\CP\SearchConsoleController;
use JothamLec\MarketingToolkit\Http\Controllers\CP\ToolbarController;

// Permissions are checked here (Statamic's permissions answer Laravel's Gate);
// a controller checks only what a route can't say, such as editing the addon's settings.
Route::name('mt.')->prefix('marketing-toolkit')->group(function () {
    Route::get('/', OverviewController::class)->middleware('can:view marketing toolkit')->name('index');
    Route::post('preview', [PreviewController::class, 'meta'])->name('preview.meta');

    Route::middleware('can:manage marketing toolkit redirects')->group(function () {
        Route::get('redirects', [RedirectsController::class, 'index'])->name('redirects.index');
        Route::get('redirects/listing', [RedirectsController::class, 'listing'])->name('redirects.listing');
        Route::get('redirects/create', [RedirectsController::class, 'create'])->name('redirects.create');
        Route::post('redirects', [RedirectsController::class, 'store'])->name('redirects.store');
        Route::get('redirects/{redirect}', [RedirectsController::class, 'edit'])->whereNumber('redirect')->name('redirects.edit');
        Route::patch('redirects/{redirect}', [RedirectsController::class, 'update'])->whereNumber('redirect')->name('redirects.update');
        Route::get('redirects/export', [RedirectsController::class, 'export'])->name('redirects.export');
        Route::post('redirects/import', [RedirectsController::class, 'import'])->name('redirects.import');
        Route::post('redirects/choice', [RedirectsController::class, 'choice'])->name('redirects.choice');
    });

    Route::post('actions', [ActionController::class, 'run'])->name('actions.run');
    Route::post('actions/list', [ActionController::class, 'bulkActions'])->name('actions.bulk');

    Route::post('preview/card', [PreviewController::class, 'card'])->name('preview.card');

    // Asked on every save of an entry or term: answers "no change" without the permission.
    Route::post('redirects/check', [RedirectsController::class, 'check'])->name('redirects.check');

    Route::middleware('can:view marketing toolkit')->group(function () {
        Route::get('404s', [NotFoundController::class, 'index'])->name('404s.index');
        Route::get('404s/listing', [NotFoundController::class, 'listing'])->name('404s.listing');

        Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::post('reports', [ReportsController::class, 'run'])->middleware('can:run marketing toolkit reports')->name('reports.run');
        Route::get('reports/{report}', [ReportsController::class, 'show'])->whereNumber('report')->name('reports.show');
        Route::post('reports/{report}/progress', [ReportsController::class, 'progress'])->whereNumber('report')->name('reports.progress');
        Route::get('reports/{report}/pages', [ReportsController::class, 'pages'])->whereNumber('report')->name('reports.pages');
        Route::get('reports/{report}/export', [ReportsController::class, 'export'])->whereNumber('report')->name('reports.export');
        // The Reports page's Settings tab: the controller checks the addon's settings permission.
        Route::post('reports/settings', [ReportsController::class, 'saveSettings'])->name('reports.settings');

        Route::get('search-console', [SearchConsoleController::class, 'index'])->name('search-console.index');
    });

    Route::post('search-console/key', [SearchConsoleController::class, 'key'])->name('search-console.key');
    Route::delete('search-console/key', [SearchConsoleController::class, 'forgetKey'])->name('search-console.key.forget');
    Route::post('search-console/property', [SearchConsoleController::class, 'property'])->name('search-console.property');
    Route::post('search-console/check', [SearchConsoleController::class, 'check'])->name('search-console.check');
    Route::post('search-console/import', [SearchConsoleController::class, 'import'])->name('search-console.import');

    // The front-end toolbar's links, on a multi-site install: selects the page's site, then opens the screen.
    Route::get('toolbar/go', [ToolbarController::class, 'go'])->name('toolbar.go');
});
