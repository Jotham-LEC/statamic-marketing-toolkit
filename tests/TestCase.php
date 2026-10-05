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
