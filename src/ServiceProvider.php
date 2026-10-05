<?php

namespace JothamLec\Seo;

use JothamLec\Seo\Actions\CreateRedirect;
use JothamLec\Seo\Actions\DeleteSeoRecords;
use JothamLec\Seo\Commands\Install;
use JothamLec\Seo\Cp\Navigation;
use JothamLec\Seo\Fieldtypes\SeoPreview;
use JothamLec\Seo\Http\Middleware\HandleMissing;
use JothamLec\Seo\Http\Middleware\TrailingSlash;
use JothamLec\Seo\Listeners\FlushSitemap;
use JothamLec\Seo\Listeners\RedirectChangedUris;
use JothamLec\Seo\Tags\Seo;
use JothamLec\Seo\Widgets\SeoWidget;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\TermDeleted;
use Statamic\Events\TermSaved;
use Statamic\Facades\Permission;
use Statamic\Facades\URL;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $viewNamespace = 'seo';

    protected $tags = [Seo::class];

    protected $commands = [Install::class];

    protected $fieldtypes = [SeoPreview::class];

    protected $widgets = [SeoWidget::class];

    protected $actions = [DeleteSeoRecords::class, CreateRedirect::class];

    protected $vite = [
        'input' => ['resources/js/addon.js', 'resources/css/addon.css'],
        'publicDirectory' => 'resources/dist',
    ];

    protected $middlewareGroups = [
        'statamic.web' => [TrailingSlash::class, HandleMissing::class],
    ];

    protected $listen = [
        EntrySaved::class => [FlushSitemap::class],
        EntryDeleted::class => [FlushSitemap::class],
        TermSaved::class => [FlushSitemap::class],
        TermDeleted::class => [FlushSitemap::class],
        CollectionTreeSaved::class => [FlushSitemap::class],
    ];

    protected $subscribe = [RedirectChangedUris::class];

    public function register(): void
    {
        parent::register();

        $this->app->bind(SiteSeo::class, fn ($app) => $app->build(config('seo.class') ?: SiteSeo::class));

        // One instance, so what it learns while content saves is still there once it has saved.
        $this->app->singleton(RedirectChangedUris::class);
    }

    public function bootAddon(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (config('seo.trailing_slash') === 'add') {
            URL::enforceTrailingSlashes();
        }

        Permission::extend(fn () => Permission::group('seo', 'SEO', function () {
            Permission::register('view seo')->label('View SEO overview, reports and 404s');
            Permission::register('manage seo redirects')->label('Manage redirects');
            Permission::register('run seo reports')->label('Run SEO reports');
        }));

        Navigation::register();
    }
}
