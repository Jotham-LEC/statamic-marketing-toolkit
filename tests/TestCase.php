<?php

namespace JothamLec\Seo\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JothamLec\Seo\ServiceProvider;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk, RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('app.env', 'production');
        $app['config']->set('cache.default', 'array');
        // Like the file and Redis stores sites use: each read is a fresh copy, so
        // the Stache never hands back the very object a test is changing.
        $app['config']->set('cache.stores.array.serialize', true);
        $app['config']->set('database.default', 'testing');

        // SEO_TEST_DB=pgsql runs the suite on Postgres (DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD).
        if (env('SEO_TEST_DB') === 'pgsql') {
            $app['config']->set('database.connections.testing', [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', 5432),
                'database' => env('DB_DATABASE', 'seo_test'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'disable',
            ]);
        }

        // A template and layout, so a page that exists renders instead of 404ing.
        $app['config']->set('view.paths', [__DIR__.'/fixtures/views']);
        $app['config']->set('statamic.editions.pro', false);
        $app['config']->set('filesystems.disks.assets', [
            'driver' => 'local',
            'root' => __DIR__.'/__fixtures__/dev-null/assets',
            'url' => '/assets',
            'visibility' => 'public',
        ]);
    }
}
