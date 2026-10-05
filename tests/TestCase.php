<?php

namespace JothamLec\Seo\Tests;

use JothamLec\Seo\ServiceProvider;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('app.env', 'production');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('statamic.editions.pro', false);
        $app['config']->set('filesystems.disks.assets', [
            'driver' => 'local',
            'root' => __DIR__.'/__fixtures__/dev-null/assets',
            'url' => '/assets',
            'visibility' => 'public',
        ]);
    }
}
