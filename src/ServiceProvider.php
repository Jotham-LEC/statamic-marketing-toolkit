<?php

namespace JothamLec\Seo;

use JothamLec\Seo\Commands\Install;
use JothamLec\Seo\Http\Middleware\TrailingSlash;
use JothamLec\Seo\Listeners\FlushSitemap;
use JothamLec\Seo\Tags\Seo;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\TermDeleted;
use Statamic\Events\TermSaved;
use Statamic\Facades\URL;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $viewNamespace = 'seo';

    protected $tags = [Seo::class];

    protected $commands = [Install::class];

    protected $middlewareGroups = [
        'statamic.web' => [TrailingSlash::class],
    ];

    protected $listen = [
        EntrySaved::class => [FlushSitemap::class],
        EntryDeleted::class => [FlushSitemap::class],
        TermSaved::class => [FlushSitemap::class],
        TermDeleted::class => [FlushSitemap::class],
        CollectionTreeSaved::class => [FlushSitemap::class],
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(SiteSeo::class, fn ($app) => $app->build(config('seo.class') ?: SiteSeo::class));
    }

    public function bootAddon(): void
    {
        if (config('seo.trailing_slash') === 'add') {
            URL::enforceTrailingSlashes();
        }
    }
}
