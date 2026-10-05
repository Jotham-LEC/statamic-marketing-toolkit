<?php

use Illuminate\Support\Facades\Route;
use JothamLec\Seo\Http\Controllers\CP\OverviewController;
use JothamLec\Seo\Http\Controllers\CP\PreviewController;

Route::name('seo.')->prefix('seo')->group(function () {
    Route::get('/', OverviewController::class)->name('index');
    Route::post('preview', [PreviewController::class, 'meta'])->name('preview.meta');
    Route::post('preview/card', [PreviewController::class, 'card'])->name('preview.card');
});
