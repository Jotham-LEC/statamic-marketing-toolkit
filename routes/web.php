<?php

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use JothamLec\MarketingToolkit\Http\Controllers\IndexNowKeyController;
use JothamLec\MarketingToolkit\Http\Controllers\OgImageController;
use JothamLec\MarketingToolkit\Http\Controllers\RobotsController;
use JothamLec\MarketingToolkit\Http\Controllers\SitemapController;
use JothamLec\MarketingToolkit\IndexNow\IndexNow;

/*
 * Statamic registers these with its front-end routes (the `web` group), ahead
 * of the catch-all. They aren't pages: Statamic's own `statamic.web`
 * middleware, static caching included, runs only for the catch-all. None of
 * them needs a session or a CSRF token, and a Set-Cookie header would stop
 * Cloudflare and browsers from caching the images and the sitemap.
 */
Route::withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    AddQueuedCookiesToResponse::class,
    // Laravel 13's CSRF middleware, and its older names; Statamic's own routes list the same three.
    'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
    'Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken',
    'App\\Http\\Middleware\\VerifyCsrfToken',
])
    ->name('seo.')
    ->group(function () {
        if (config('seo.sitemap.enabled')) {
            Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
            Route::get('sitemap_{page}.xml', [SitemapController::class, 'page'])->whereNumber('page')->name('sitemap.page');
        }

        if (config('seo.robots_txt') && ! file_exists(public_path('robots.txt'))) {
            Route::get('robots.txt', RobotsController::class)->name('robots');
        }

        if (config('seo.indexnow.enabled')) {
            Route::get(app(IndexNow::class)->key().'.txt', IndexNowKeyController::class)->name('indexnow.key');
        }

        if (config('seo.og.enabled')) {
            Route::get('og.png', OgImageController::class)->name('og.home');
            Route::get('og/{path}.png', OgImageController::class)->where('path', '.*')->name('og');
        }
    });
