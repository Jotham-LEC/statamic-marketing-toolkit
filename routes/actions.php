<?php

use Illuminate\Support\Facades\Route;
use JothamLec\MarketingToolkit\Http\Controllers\ToolbarController;

/*
 * Statamic puts these routes under /!/marketing-toolkit/, in the `web` group
 * (which gives them the session and CSRF protection) but outside `statamic.web`,
 * so static caching never stores them. They are registered whatever the config
 * says, as routes/web.php's are, and the controller answers 404 while the
 * toolbar is off.
 */
Route::name('mt.')->group(function () {
    Route::get('toolbar', [ToolbarController::class, 'show'])->middleware('throttle:60,1')->name('toolbar');
    Route::post('toolbar/cache', [ToolbarController::class, 'refreshCache'])->middleware(['throttle:60,1', 'can:access cache utility'])->name('toolbar.cache');
});
