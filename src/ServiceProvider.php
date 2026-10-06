<?php

namespace JothamLec\Seo;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use JothamLec\Seo\Actions\CreateRedirect;
use JothamLec\Seo\Actions\DeleteSeoRecords;
use JothamLec\Seo\Commands\Install;
use JothamLec\Seo\Commands\Report;
use JothamLec\Seo\Commands\SearchConsole;
use JothamLec\Seo\Cp\Navigation;
use JothamLec\Seo\Fieldtypes\SeoPreview;
use JothamLec\Seo\Http\Middleware\HandleMissing;
use JothamLec\Seo\IndexNow\IndexNow;
use JothamLec\Seo\Listeners\FlushSitemap;
use JothamLec\Seo\Listeners\RedirectChangedUris;
use JothamLec\Seo\Listeners\SubmitToIndexNow;
use JothamLec\Seo\Reports\ReportSettings;
use JothamLec\Seo\SearchConsole\Client as SearchConsoleClient;
use JothamLec\Seo\Support\Config;
use JothamLec\Seo\Tags\Seo;
use JothamLec\Seo\Widgets\SeoWidget;
use Statamic\Events\CollectionSaved;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntryScheduleReached;
use Statamic\Events\TaxonomySaved;
use Statamic\Events\StacheCleared;
use Statamic\Events\TermDeleted;
use Statamic\Events\TermSaved;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $viewNamespace = 'seo';

    protected $tags = [Seo::class];

    protected $commands = [Install::class, Report::class, SearchConsole::class];

    protected $fieldtypes = [SeoPreview::class];

    protected $widgets = [SeoWidget::class];

    protected $actions = [DeleteSeoRecords::class, CreateRedirect::class];

    protected $vite = [
        'input' => ['resources/js/addon.js', 'resources/css/addon.css'],
        'publicDirectory' => 'resources/dist',
    ];

    protected $middlewareGroups = [
        'statamic.web' => [HandleMissing::class],
    ];

    protected $listen = [
        EntrySaved::class => [FlushSitemap::class, SubmitToIndexNow::class],
        EntryDeleted::class => [FlushSitemap::class, SubmitToIndexNow::class],
        // A scheduled entry going live, or an expiring one going away (Statamic's scheduler).
        EntryScheduleReached::class => [FlushSitemap::class, SubmitToIndexNow::class],
        TermSaved::class => [FlushSitemap::class, SubmitToIndexNow::class],
        TermDeleted::class => [FlushSitemap::class, SubmitToIndexNow::class],
        CollectionTreeSaved::class => [FlushSitemap::class],
        // A new route moves every entry or term in it.
        CollectionSaved::class => [FlushSitemap::class],
        TaxonomySaved::class => [FlushSitemap::class],
    ];
        // A deploy clears the Stache; the rules may have changed with the code.
        StacheCleared::class => [FlushSitemap::class],

    protected $subscribe = [RedirectChangedUris::class];

    public function register(): void
    {
        parent::register();

        $this->app->bind(SiteSeo::class, fn ($app) => $app->build(config('seo.class') ?: SiteSeo::class));

        $this->app->bind(ReportSettings::class, fn () => new ReportSettings);

        // One instance, so what it learns while content saves is still there once it has saved.
        $this->app->singleton(RedirectChangedUris::class);

        // One per request: it gathers the changed addresses until the response is out.
        $this->app->singleton(IndexNow::class);
    }

    /**
     * The site's config/seo.php over the addon's, merged at every depth
     * (Support\Config) rather than Laravel's one level, so a site states only
     * what it changes, even inside `og` or `robots`.
     */
    protected function mergeConfigFrom($path, $key)
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');
        $config->set($key, Config::merge(require $path, (array) $config->get($key, [])));
    }

    public function bootAddon(): void
    {
        $this->app->terminating(fn () => $this->app->make(IndexNow::class)->flush());

        // A queue worker doesn't terminate between jobs: send what each job changed once it is done.
        // (A sync job is part of the request or command that ran it, which terminates as usual.)
        Queue::after(function (JobProcessed $event) {
            if ($event->connectionName !== 'sync') {
                $this->app->make(IndexNow::class)->flush();
            }
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Permission::extend(fn () => Permission::group('seo', 'SEO', function () {
            Permission::register('view seo')->label('View SEO overview, reports and 404s');
            Permission::register('manage seo redirects')->label('Manage redirects');
            Permission::register('run seo reports')->label('Run SEO reports');
        }));

        Navigation::register();
    }

    /**
     * Reports on the schedule set under Tools → Addons → SEO.
     */
    protected function schedule($schedule)
    {
        $settings = app(ReportSettings::class);
        $time = substr((string) $settings->get('schedule_time'), 0, 5) ?: '03:00';
        $day = array_search($settings->get('schedule_day'), ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], true);

        $event = match ($settings->get('schedule')) {
            'daily' => $schedule->command('statamic:seo:report')->dailyAt($time),
            'weekly' => $schedule->command('statamic:seo:report')->weeklyOn($day === false ? 1 : $day, $time),
            default => null,
        };

        $event?->withoutOverlapping()->runInBackground();

        // Search Console's numbers, daily, once it is set up.
        if (app(SearchConsoleClient::class)->configured()) {
            $schedule->command('statamic:seo:search-console')->dailyAt('04:30')->withoutOverlapping();
        }
    }
}
